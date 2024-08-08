<?php
namespace App\Controller;

/*use http\Env\Response;*/

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class MainController extends AbstractController
{
    private $passwordHasher;
    private $entityManager;
    public function __construct(UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager)
    {
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
    }
    #[Route('/profile', name: 'profile.index', methods: ['GET'])]
    public function profile(
        SessionInterface $session,
        CsrfTokenManagerInterface $csrfTokenManager,
        EntityManagerInterface $entityManager
    ): Response {
        /// Получаем id пользователя из сессии
        $userId = $session->get('user_id');
        $csrfToken = $csrfTokenManager->getToken('authenticate')->getValue();
        if (!$userId) {
            // Если пользователь не авторизован, перенаправляем на страницу логина

            return $this->redirectToRoute('login.index');
        }

        // Получаем репозиторий сущности User
        $userRepository = $entityManager->getRepository(User::class);

        // Находим пользователя по id
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error','Пользователь не найден');
            return $this->redirectToRoute('login.index');
        }

        $userFio = $user->getFio();

        $userLogin= $user->getLogin();
        $userPhone= $user->getPhone();
        $userEmail= $user->getEmail();

        $userId= $user->getId();

        // Возвращаем результат в шаблон
        return $this->render('ForUser/mainPage.html.twig', [
            'userFio' => $userFio,
            'userLogin' => $userLogin,
            'userId' => $userId,
            'userPhone' => $userPhone,
            'userEmail' => $userEmail,
            'csrf_token' => $csrfToken,
        ]);
    }

    #[Route('/profile', name: 'profile_put.store', methods: ['PUT', 'POST'])]
    public function profilePut(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): Response {
        // Получаем id пользователя из сессии
        $userId = $session->get('user_id');

        if (!$userId) {
            // Если пользователь не авторизован, перенаправляем на страницу логина
            $this->addFlash('error', 'Нет доступа');
            return $this->redirectToRoute('login.index');
        }

        // Получаем данные из формы
        $login = $request->request->get('login');
        $fio = $request->request->get('fio');
        $phone = $request->request->get('phone');
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        // Находим пользователя по id
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('Пользователь не найден');
        }

        // Обновляем данные пользователя
        $user->setLogin($login);
        $user->setFio($fio);
        $user->setPhone($phone);
        $user->setEmail($email);

        // Обрабатываем пароль, если он задан
        if (!empty($password)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }


        // Сохраняем изменения
        $this->entityManager->flush();

        $this->addFlash('success', 'Информация успешно обновлена!');

        // Перенаправляем на профиль или другую страницу
        return $this->redirectToRoute('profile.index');
    }

    #[Route('/allUsers', name: 'allUsers.index', methods: ['GET'])]
    public function allUsers(
        SessionInterface $session,
        CsrfTokenManagerInterface $csrfTokenManager,
        EntityManagerInterface $entityManager
    ): Response {
        // Получаем id пользователя из сессии
        $userId = $session->get('user_id');
        $csrfToken = $csrfTokenManager->getToken('authenticate')->getValue();

        if (!$userId) {
            // Если пользователь не авторизован, перенаправляем на страницу логина
            return $this->redirectToRoute('login.index');
        }

        // Получаем репозиторий сущности User
        $userRepository = $entityManager->getRepository(User::class);

        // Находим всех пользователей
        $users = $userRepository->findAll();

        // Возвращаем результат в шаблон
        return $this->render('ForUser/allUsers.html.twig', [
            'users' => $users,
            'userId' => $userId,
            'csrf_token' => $csrfToken,
        ]);
    }

    #[Route('/allUsers/{action}', name: 'allUsers.store', methods: ['PUT', 'DELETE', 'POST'])]
    public function allUsersPost(Request $request, $action,
                             SessionInterface $session,
                             EntityManagerInterface $entityManager) {
        $methods = $request->request->get('_method');
        if ($methods && $action === 'delete') {
            // Получаем id пользователя из сессии
            $userId = $session->get('user_id');

            if (!$userId) {
                // Если пользователь не авторизован, перенаправляем на страницу логина
                $this->addFlash('error', 'Нет доступа');
                return $this->redirectToRoute('login.index');
            }

            // Получаем id пользователя, которого нужно удалить
            $deleteUserId = $request->request->get('user_id');

            // Находим пользователя по id
            $user = $entityManager->getRepository(User::class)->find($deleteUserId);

            if (!$user) {
                throw $this->createNotFoundException('Пользователь не найден');
            }

            // Удаляем пользователя
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Пользователь успешно удален!');

            // Перенаправляем на страницу с пользователями
            return $this->redirectToRoute('allUsers.index');
        }

        if ($methods && $action === 'update') {
            // Получаем id пользователя из сессии
            $userId = $session->get('user_id');

            if (!$userId) {
                // Если пользователь не авторизован, перенаправляем на страницу логина
                $this->addFlash('error', 'Нет доступа');
                return $this->redirectToRoute('login.index');
            }

            // Получаем данные из формы
            $login = $request->request->get('login');
            $user_id = $request->request->get('user_id');
            $fio = $request->request->get('fio');
            $phone = $request->request->get('phone');
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            // Находим пользователя по id
            $user = $this->entityManager->getRepository(User::class)->find($user_id);
// Проверяем, существует ли другой пользователь с таким же логином
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['login' => $login]);
            if (!$user) {
                $this->addFlash('error', 'Пользователь не найден');
                return $this->redirectToRoute('login.index');
            }
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                // Если найден другой пользователь с таким же логином, логин занят
                $this->addFlash('error', 'Логин уже занят!');
                return $this->redirectToRoute('allUsers.index');
            }
            // Обновляем данные пользователя
            $user->setLogin($login);
            $user->setFio($fio);
            $user->setPhone($phone);
            $user->setEmail($email);

            // Обрабатываем пароль, если он задан
            if (!empty($password)) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
            }


            // Сохраняем изменения
            $this->entityManager->flush();

            $this->addFlash('success', 'Информация успешно обновлена!');

            // Перенаправляем на профиль или другую страницу
            return $this->redirectToRoute('allUsers.index');
        }
        // Возвращаем перенаправление по умолчанию или ошибку
        $this->addFlash('error', 'Неверный запрос');
        return $this->redirectToRoute('allUsers.index');
    }

    #[Route('/exit', name: 'exit.store', methods: ['POST'])]
    public function exit(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager
    ): Response {
        // Получаем id пользователя из сессии
        $userId = $session->get('user_id');

        if (!$userId) {
            // Если пользователь не авторизован, перенаправляем на страницу логина
            $this->addFlash('error', 'Нет доступа');
            return $this->redirectToRoute('login.index');
        }
        $session->remove('user_id');
        $this->addFlash('error', 'Вы успешно вышли из сессии!');
        return $this->redirectToRoute('login.index');
    }


}