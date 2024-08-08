<?php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthCheckListener
{
    private $router;
    private $session;

    public function __construct(RouterInterface $router, SessionInterface $session)
    {
        $this->router = $router;
        $this->session = $session;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        // Проверяем, что запрос является основным запросом (не подзапрос)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');

        // Перечисляем маршруты, которые требуют авторизации
        $protectedRoutes = ['profile.index', 'profile_put.store'];

        if (in_array($currentRoute, $protectedRoutes)) {
            // Проверяем, авторизован ли пользователь
            $userId = $this->session->get('user_id');

            if (!$userId) {
                $event->setResponse(new RedirectResponse($this->router->generate('login_page')));
            }
        }
    }
}
