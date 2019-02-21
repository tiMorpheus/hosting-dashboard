# Installation #

1. Go to whmcs/modules/addons and use `git clone [url]` to clone files.

2. Use composer to install dependecies (`composer install`).

3. Go to admin addons management page, enable addon and configure setting: api key and url to dashboard which is used for redirects.

# About #

This module extend abilitites of whmcs api and provide functionality for:

- initiating trials (extend addOrder),
- retrieving trial services statuses (extend GetClientsProduct) 
- retrieving info about which products can be used as trials and if not - reason why (extend GetProducts, but response differs a bit from original (returns array), fix it later)

## Methods description ##
[You should pass `Auth-Token` header with the rigth value of token configured in whmcs]

- addOrder

    Additional parameter can be passed to create "pending" trial which will be activated only after PPBA set.
    
    Example request: POST http://whmcs.hz/modules/addons/blazing_servers/api.php?method=addOrder&clientid=1&pid=48&billingcycle=monthly&paymentmethod=paypal
    Example response:
```
{
    "result": "success",
    "orderid": 205,
    "productids": "133",
    "addonids": "",
    "domainids": "",
    "invoiceid": 220,
    "trial": false,
    "pendingTrial": false
}
```

- getClientsProduct

    Additional response field `trialStatus` can be of values "Pending trial" or "Trial period"

    Example request: GET http://whmcs.hz/modules/addons/blazing_servers/api.php?method=getClientProducts&clientid=1
    Example reponse:
```
{
    "result": "success",
    "clientid": "1",
    "serviceid": null,
    "pid": null,
    "domain": null,
    "totalresults": 37,
    "startnumber": 0,
    "numreturned": 37,
    "products": {
        "product": {
            "128": {
                "id": 128,
                "clientid": 1,
                "orderid": 200,
                "pid": 48,
                "regdate": "2018-11-07",
                "name": "Test 1",
                "translated_name": "Test 1",
                "groupname": "Test",
                "translated_groupname": "Test",
                "domain": "",
                "dedicatedip": "",
                "serverid": 0,
                "servername": "",
                "serverip": null,
                "serverhostname": null,
                "suspensionreason": "",
                "firstpaymentamount": "15.00",
                "recurringamount": "15.00",
                "paymentmethod": "paypal",
                "paymentmethodname": "PayPal",
                "billingcycle": "Monthly",
                "nextduedate": "2018-11-07",
                "status": "Pending",
                "username": "",
                "password": "",
                "subscriptionid": "",
                "promoid": 0,
                "overideautosuspend": 0,
                "overidesuspenduntil": "0000-00-00",
                "ns1": "",
                "ns2": "",
                "assignedips": "",
                "notes": "",
                "diskusage": 0,
                "disklimit": 0,
                "bwusage": 0,
                "bwlimit": 0,
                "lastupdate": "0000-00-00 00:00:00",
                "customfields": {
                    "customfield": [
                        {
                            "id": 391,
                            "name": "trial_off",
                            "translated_name": "trial_off",
                            "value": ""
                        },
                        {
                            "id": 392,
                            "name": "free_period",
                            "translated_name": "free_period",
                            "value": ""
                        },
                        {
                            "id": 393,
                            "name": "trial",
                            "translated_name": "trial",
                            "value": ""
                        },
                        {
                            "id": 394,
                            "name": "trial_category",
                            "translated_name": "trial_category",
                            "value": ""
                        }
                    ]
                },
                "configoptions": {
                    "configoption": [
                        {
                            "id": 1,
                            "option": "Scrapping robot configuration",
                            "type": "quantity",
                            "value": 10
                        }
                    ]
                },
                "trialStatus": "Pending trial"
            }
        }
    }
}
```

- getProducts

    Additional response fields: "isTrial": boolean (if marked as trial in trial addon) , "trialNotAllowedReason": "PPBA not set", "Exist trial product" or "" (if allowed)

    Example request: GET http://whmcs.hz/modules/addons/blazing_servers/api.php?method=getProducts&gid=2&userId=1&pid=48
    Example response:
```
[
    {
        "id": 48,
        "type": "hostingaccount",
        "gid": 2,
        "name": "Test 1",
        "description": "",
        "hidden": 0,
        "showdomainoptions": 1,
        "welcomeemail": 0,
        "stockcontrol": 0,
        "qty": 0,
        "proratabilling": 0,
        "proratadate": 0,
        "proratachargenextmonth": 0,
        "paytype": "recurring",
        "allowqty": 0,
        "subdomain": "",
        "autosetup": "",
        "servertype": "",
        "servergroup": 0,
        "configoption1": "",
        "configoption2": "",
        "configoption3": "",
        "configoption4": "",
        "configoption5": "",
        "configoption6": "",
        "configoption7": "",
        "configoption8": "",
        "configoption9": "",
        "configoption10": "",
        "configoption11": "",
        "configoption12": "",
        "configoption13": "",
        "configoption14": "",
        "configoption15": "",
        "configoption16": "",
        "configoption17": "",
        "configoption18": "",
        "configoption19": "",
        "configoption20": "",
        "configoption21": "",
        "configoption22": "",
        "configoption23": "",
        "configoption24": "",
        "freedomain": "",
        "freedomainpaymentterms": "",
        "freedomaintlds": "",
        "recurringcycles": 0,
        "autoterminatedays": 0,
        "autoterminateemail": 0,
        "configoptionsupgrade": 0,
        "billingcycleupgrade": "",
        "upgradeemail": 0,
        "overagesenabled": "",
        "overagesdisklimit": 0,
        "overagesbwlimit": 0,
        "overagesdiskprice": "0.0000",
        "overagesbwprice": "0.0000",
        "tax": 0,
        "affiliateonetime": 0,
        "affiliatepaytype": "",
        "affiliatepayamount": "0.00",
        "order": 1,
        "retired": 0,
        "is_featured": 0,
        "created_at": "2018-06-01 23:40:07",
        "updated_at": "2018-06-01 23:40:07",
        "customfields": {
            "customfield": []
        },
        "configoptions": {
            "configoption": [
                {
                    "id": 1,
                    "name": "Scrapping robot configuration",
                    "type": "4",
                    "options": {
                        "option": [
                            {
                                "id": 1,
                                "name": "Initial balance",
                                "recurring": null,
                                "pricing": {
                                    "USD": {
                                        "msetupfee": "0.00",
                                        "qsetupfee": "0.00",
                                        "ssetupfee": "0.00",
                                        "asetupfee": "0.00",
                                        "bsetupfee": "0.00",
                                        "tsetupfee": "0.00",
                                        "monthly": "1.00",
                                        "quarterly": "0.00",
                                        "semiannually": "0.00",
                                        "annually": "0.00",
                                        "biennially": "0.00",
                                        "triennially": "0.00"
                                    }
                                }
                            }
                        ]
                    }
                }
            ]
        },
        "isTrial": true,
        "trialNotAllowedReason": ""
    }
]
```

## Notes ##

This is related to other task, but if you want whmcs toredirect user on servers dashboard
after PPBA set - add `&redirectToServersDashboard=1` parameter to request which links to paypalbilling page (example `https://whmcs.devcopy.blazingseollc.com/paypalbilling.php?redirectToServersDashboard=1`)