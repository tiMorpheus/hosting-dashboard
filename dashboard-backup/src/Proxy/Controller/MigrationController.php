<?php

namespace Proxy\Controller;

use Axelarge\ArrayTools\Arr;
use Buzz\Browser;
use Buzz\Exception\RequestException;
use DateTime;
use ErrorException;
use Symfony\Component\HttpFoundation\JsonResponse;

class MigrationController extends AbstractController
{
    protected $emailsToNotificate = ['and.webdev@gmail.com', 'support@blazingseollc.com'];

    public function migratePackageFromAmemberToWhmcs($packageId)
    {
        $user = $this->getUser()->getDetails();
        $return = [
            'status' => 'ok',
            'errors' => [],
            'info' => []
        ];

        $this->log->debug('Migration started', ['user' => $user]);
        try {
            $sql = "SELECT pup.id, pup.country, pup.category, pup.source, 
                  ai.tm_started as period_start, ai.rebill_date as period_end, 
                  pp.ports, aii.second_price as price, aii.invoice_id
                FROM `proxy_users` u
                INNER JOIN proxy_user_packages as pup ON pup.user_id = u.id AND pup.source = 'amember'
                INNER JOIN proxy_packages pp ON pp.id = pup.package_id
                INNER JOIN banditim_amember.am_invoice ai ON ai.user_id = u.amember_id AND ai.tm_started != '' AND ai.tm_cancelled is null
                INNER JOIN banditim_amember.am_invoice_item aii ON aii.invoice_id = ai.invoice_id AND aii.item_id = pup.package_id
                INNER JOIN banditim_amember.am_access ac ON ac.invoice_item_id = aii.invoice_item_id AND ac.expire_date > NOW()
                WHERE u.id = ? AND ai.rebill_date >= NOW() AND pup.id = ?
                GROUP BY pup.user_id, pup.country, pup.category";
            $package = $this->getConn()->executeQuery($sql, [$user[ 'userId' ], $packageId])->fetch();

            // 2nd approach, load any valid product
            if (!$package) {
                $this->log->notice('No valid invoice is found in amember, trying to find product', ['packageId' => $packageId]);

                $sql = "
                    SELECT pup.id, pup.country, pup.category, pup.source, 
                      ac.begin_date as period_start, ac.expire_date as period_end, 
                      pp.ports
                    FROM `proxy_users` u
                    INNER JOIN proxy_user_packages as pup ON pup.user_id = u.id AND pup.source = 'amember'        
                    INNER JOIN proxy_packages pp ON pp.id = pup.package_id
                    INNER JOIN banditim_amember.am_access ac ON ac.product_id = pp.id
                    WHERE u.id = ? AND pup.id = ?
                    ORDER BY ac.access_id DESC
                ";
                $package = $this->getConn()->executeQuery($sql, [$user[ 'userId' ], $packageId])->fetch();

                if ($package) {
                    $this->log->notice('Found valid product in amember', ['package' => $package]);

                    mail(join(', ', $this->emailsToNotificate),
                        sprintf('Migration with no invoice of "%s-%s" package for "%s" (%s..%s)',
                            $package[ 'country' ], $package[ 'category' ], $user[ 'login' ],
                            $package[ 'period_start' ], $package[ 'period_end' ]),
                            sprintf("%s\n%s",
                                $this->app['config.amember.url'] . '/admin-user-payments/index/user_id/' . $user['amemberId'],
                                json_encode([
                                    'user'    => $user,
                                    'package' => Arr::except($package, ['id', 'source'])
                            ], JSON_PRETTY_PRINT)
                        ));

                    if ((new DateTime($package[ 'period_end' ]))->getTimestamp() < time()) {
                        unset($package[ 'period_end' ]);
                    }
                }
            }

            if (!$package) {
                throw new ErrorException('No valid invoice found in AMember');
            }

            // Get price
            $response = $this->app[ 'integration.whmcs.plugin' ]->getPricingTiers();
            if (empty($response[ 'pricing' ])) {
                throw new ErrorException('No pricing tiers received, response - ' . json_encode($response));
            }
            $pricingTiers = $response[ 'pricing' ];
            $productId = false;
            foreach ($pricingTiers as $product) {
                $category = $package[ 'category' ];
                if ($category == 'static') {
                    $category = 'dedicated';
                }
                if ($category == 'rotate') {
                    $category = 'rotating';
                }
                if ($package[ 'country' ] == $product[ 'meta' ][ 'country' ] and $category == $product[ 'meta' ][ 'category' ]) {
                    $productId = $product[ 'productId' ];
                    break;
                }
            }
            if (!$productId) {
                throw new ErrorException(sprintf('Not found category or country (%s) in WHMCS pricing table!',
                    $package[ 'country' ] . '-' . $package[ 'category' ]));
            }

            // Import a package into whmcs
            $orderInformation = $this->app[ 'integration.whmcs.plugin' ]->updatePackage(
                $user[ 'whmcsId' ],
                $productId,
                $package[ 'ports' ],
                null,
                null,
                $this->getUrl('callback_whmcs', ['userId' => $user[ 'userId' ]])
            );

            if ((!empty($orderInformation[ 'invoiceId' ]) or !empty($orderInformation[ 'noInvoice' ])) and
                !empty($orderInformation[ 'info' ][ 'serviceId' ])
            ) {

                // Mark invoice as paid
                if (!empty($orderInformation[ 'invoiceId' ])) {
                    $this->app[ 'integration.whmcs.api' ]->api('updateinvoice',
                        ['invoiceid' => $orderInformation[ 'invoiceId' ], 'status' => 'Paid']);
                    $this->app[ 'integration.whmcs.api' ]->api('modulecreate',
                        ['accountid' => $orderInformation[ 'info' ][ 'serviceId' ]]);
                }

                // Update product due date
                $this->app[ 'integration.whmcs.api' ]->api('updateclientproduct', array_merge([
                    'serviceid'       => $orderInformation[ 'info' ][ 'serviceId' ],
                    'regdate'         => (new DateTime($package[ 'period_start' ]))->format('Y-m-d'),
                ],
                    !empty($package[ 'period_end' ]) ? ['nextduedate' => $package[ 'period_end' ]] : [],
                    !empty($package[ 'price' ]) ? ['recurringamount' => $package[ 'price' ]] : []
                ));

                // Update package source
                $this->getConn()->update('proxy_user_packages', ['source' => 'whmcs'], ['id' => $package[ 'id' ]]);

                if (!empty($orderInformation[ 'noInvoice' ])) {
                    $return['errors'][] = 'Invoice was paid by credits';
                }

                // Cancel package in amember
                if (!empty($package[ 'invoice_id' ])) {
                    $tries = 10;
                    while (true) {
                        try {
                            /** @var \Buzz\Message\Response $response */
                            $response = (new Browser())->submit($this->app[ 'config.amember.url' ] . '/amember-stop-recurring.php',
                                ['invoice-id' => $package[ 'invoice_id' ]], 'GET');

                            if (200 !== $response->getStatusCode()) {
                                throw new RequestException(sprintf('Response code - %s, (%s)',
                                    $response->getStatusCode(), $response->getContent()));
                            }

                            $this->log->debug('Stop recurring response', ['response' => $response->getContent()]);
                            break;
                        }
                        catch (RequestException $e) {
                            $tries--;
                            $this->log->warn('Stop recurring RequestException', ['tries' => $tries]);

                            // Stop trying
                            if ($tries <= 0) {
                                throw new ErrorException('Subscription has no been cancelled due network issue: ' . $e->getMessage());
                            }

                            sleep(1);
                        }
                    }
                    /** @noinspection PhpUndefinedVariableInspection */
                    $response = $response->getContent();
                    $response = @json_decode($response, true);
                    if (empty($response[ 'invoice' ])) {
                        $return[ 'errors' ][] = 'Subscription has not been cancelled: ' . ($response ? json_encode($response) : $response);
                    }
                    else {
                        // Invoice
                        if (!empty($response[ 'invoice' ][ 'success' ])) {
                            // $this->addFlashSuccess('Invoice has been cancelled');
                        }
                        else {
                            $return[ 'error' ][] = 'Invoice has not been cancelled. ' .
                                (!empty($response[ 'invoice' ][ 'message' ]) ? $response[ 'invoice' ][ 'message' ] : '');
                        }

                        // Subscription
                        if (!empty($response[ 'payment' ][ 'success' ]) and empty($response[ 'payment' ][ 'redirect' ])) {
                            $return[ 'info' ][] = 'Previous payment subscription has been cancelled';
                        }
                        else {
                            // Manual cancellation
                            if (!empty($response[ 'payment' ][ 'redirect' ])) {
                                $return[ 'errors' ][] = "Previous payment subscription has not been cancelled" .
                                    (!empty($response[ 'payment' ][ 'message' ]) ?
                                        (' due error:<br>' . $response[ 'payment' ][ 'message' ]) : '') .
                                    "<br><br>Please cancel it manually 
                            <a href='{$response['payment']['redirect']}' target='_blank'>by this link</a>";
                            }
                            else {
                                $return[ 'errors' ][] = 'Previous payment subscription has not been cancelled. ' .
                                    (!empty($response[ 'payment' ][ 'message' ]) ? $response[ 'payment' ][ 'message' ] : '');
                            }
                        }
                    }
                }

                $return['info'][] = 'Plan has been migrated to WHMCS';
            }
            elseif (!empty($orderInformation[ 'error' ]) and !empty($orderInformation[ 'alert' ])) {
                $return['errors'][] = $orderInformation[ 'message' ];
            }
            else {
                throw new ErrorException('Error occured, information - ' . json_encode($orderInformation));
            }
        }
        catch (ErrorException $e) {
            $this->log->err($e->getMessage(), [$e]);
            $return['status'] = 'error';
            $return['errors'][] = $e->getMessage();

            return new JsonResponse($return);
        }

        return new JsonResponse($return);
    }
}
