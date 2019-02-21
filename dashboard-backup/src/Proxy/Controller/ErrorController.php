<?php

namespace Proxy\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorController extends AbstractController
{

    public function handleException(\Exception $exception)
    {
        try {
            if ($exception instanceof NotFoundHttpException) {
                return $this->handle404();
            }

            if ($exception instanceof BadRequestHttpException) {
                return $this->handleBadRequest($exception);
            }

            return new Response($this->renderView(
                500,
                $this->app['debug'] ?
                    'Oops, a bug probably' :
                    "Oops, an error has occurred... <br/>But don't worry, we are aware of this already!",
                ($this->app['debug'] ? '<strong>' . $exception->getMessage() . '</strong><br/>' : '') .
                $this->buildDebugText($exception),
                ['back', 'contact']
            ), 500);
        }
        catch (\ErrorException $e) {
            $this->log->error('Exception during rendering exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    protected function handle404()
    {
        return new Response($this->renderView(404, 'That page is not found!'), 404);
    }

    protected function handleBadRequest(BadRequestHttpException $exception)
    {
        return new Response($this->renderView(
            400,
            'The request is incorrect',
            '<strong>' . $exception->getMessage() . '</strong><br/>' . $this->buildDebugText($exception),
            ['contact']
        ), 500);
    }

    protected function buildDebugText(\Exception $exception)
    {
        return $this->app['debug'] ?
            ('at ' .
                ltrim(str_replace(realpath(__DIR__ . '/../../../'), '', $exception->getFile()), '\\/') .
                ':' . $exception->getLine() . '<br/>' .
                (($this->log and $this->log->getRequestUid()) ?
                    ('error track code is: <strong>' . $this->log->getRequestUid() . '</strong></br>') : '') .
                ('<br>Your beautiful trace is <pre>' . $exception->getTraceAsString() . '</pre>')

            ) :
            (($this->log and $this->log->getRequestUid()) ?
                ('Error track code is: <strong>' . $this->log->getRequestUid() . '</strong>') :
                null);
    }

    protected function renderView(
        $code,
        $text,
        $note = '',
        array $buttons = ['back', 'home', 'contact'],
        array $variables = []
    ) {
        if (!$note) {
            $note = 'Why don\'t you try one of these pages instead?';
        }

        return $this->app[ 'twig' ]->render('error.html.twig', [
            'errorCode' => $code,
            'errorText' => $text,
            'errorNote' => $note,
            'buttons'   => $buttons,
            'vars'      => $variables
        ]);
    }
}
