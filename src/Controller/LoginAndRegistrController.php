<?php

namespace App\Controller;

/*use http\Env\Response;*/

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Service\UserService;

class LoginAndRegistrController extends AbstractController
{
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    #[Route('/', name: 'login.index', methods: ['GET', 'HEAD'])]
    public function login(CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $csrfToken = $csrfTokenManager->getToken('authenticate')->getValue();

        return $this->render('login.html.twig', [
            'csrf_token' => $csrfToken,
        ]);

    }

    #[Route('/', name: 'login_post.store', methods: ['POST'])]
    public function loginPost(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session
    ): Response {
        $data = $request->request->all();
        // Получаем данные из формы
        $login = $data['login'];
        $password = $data['password'];
        $csrfToken = $data['_token'];
        // Проверяем CSRF токен
        if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('authenticate', $csrfToken))) {
            $this->addFlash('form_data', $data);
            $this->addFlash('error','Устаревший или нерпавильный токен');

            return $this->redirectToRoute('login.index');
        }
// Ищем пользователя в базе данных по номеру телефона
        $user = $entityManager->getRepository(User::class)->findOneBy(['login' => $login]);

        // Если пользователь не найден или пароль не совпадает, возвращаем ошибку
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('form_data', $data);
            $this->addFlash('error','Неправильный логин или пароль');

            return $this->redirectToRoute('login.index');
        }

        // Создаем сессию с id пользователя
        $session = $request->getSession();
        $session->set('user_id', $user->getId());
        $session->remove('form_data');
        $this->addFlash('success', 'Вы успешно вошли!');
        // Перенаправляем пользователя на другой маршрут, например, на его профиль
        return $this->redirectToRoute('profile.index');
        // Обработка данных формы

    }


    #[Route('/registr', name: 'registr.index', methods: ['GET', 'HEAD'])]
    public function registr(CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $csrfToken = $csrfTokenManager->getToken('authenticate')->getValue();

        return $this->render('registr.html.twig', [
            'csrf_token' => $csrfToken,
        ]);
    }

    #[Route('/registr', name: 'registr_post.store', methods: ['POST'])]
    public function registrPost(
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordHasherInterface $passwordHasher)
    : Response {
        $data = $request->request->all();
        // Проверяем CSRF токен
        $csrfToken = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('authenticate', $csrfToken))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token');
        }

        $login = $data['login'];
        $fio = $data['fio'];
        $phone = $data['phone'];
        $email = $data['email'];
        $password = $data['password'];
        $passwordConfirmation = $data['password_confirmation'];

        $errors = [];

        // Проверка данных
        if (!preg_match('/^[а-яА-Я\s]+$/u', $fio)) {
            $errors[] = 'ФИО должно содержать только русские буквы и пробелы.';
        }

        if (!preg_match('/^\d+$/', $phone) || strlen($phone) < 11) {
            $errors[] = 'Телефон должен содержать только цифры и быть не менее 11 символов.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный адрес электронной почты.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать не менее 6 символов.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'Пароли не совпадают.';
        }

        if (!empty($errors)) {
            // Если есть ошибки, перенаправляем обратно с сообщениями об ошибках
            $this->addFlash('form_data', $data);
            $this->addFlash('error', implode(' ', $errors));

            return $this->redirectToRoute('registr.index');
        }

        $user = $this->userService->addUser($login, $fio, $phone, $email, $password);
        $session = $request->getSession();
        $session->remove('form_data');
        $this->addFlash('success', 'Вы успешно зарегистрировались!');
        // Перенаправляем на страницу авторизации или другую страницу
        return $this->redirectToRoute('login.index');
    }

}