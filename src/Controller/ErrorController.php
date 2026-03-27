<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Log\Log;

/**
 * Error Handling Controller
 *
 * Controller used by ExceptionRenderer to render error responses.
 */
class ErrorController extends AppController
{
    public function initialize(): void
    {
    }

    public function beforeFilter(EventInterface $event): void
    {
    }

    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);
        $this->viewBuilder()->setTemplatePath('Error');

        // Generate unique error code
        $errorCode = 'ERR-' . strtoupper(bin2hex(random_bytes(4)));

        // Gather error details for logging
        $error = $this->viewBuilder()->getVar('error');
        $message = $this->viewBuilder()->getVar('message') ?? 'Unknown error';
        $url = $this->request->getRequestTarget();
        $statusCode = $this->response->getStatusCode();

        $logEntry = sprintf(
            "[%s] HTTP %d | URL: %s | Message: %s",
            $errorCode,
            $statusCode,
            $url,
            $message
        );
        if ($error instanceof \Throwable) {
            $logEntry .= sprintf(
                " | Exception: %s in %s:%d\n%s",
                get_class($error),
                $error->getFile(),
                $error->getLine(),
                $error->getTraceAsString()
            );
        }

        Log::error($logEntry);

        $this->set('errorCode', $errorCode);
    }

    public function afterFilter(EventInterface $event): void
    {
    }
}
