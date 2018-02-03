<?php

namespace App\Controller;

use App\Exception\GoogleTokenRefreshException;
use App\Services\Picasa;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

class DefaultController extends Controller
{
    /** @var \Google_Client */
    protected $googleClient;

    /** @var RouterInterface */
    protected $router;

    /** @var SessionInterface */
    protected $session;

    /** @var Picasa */
    protected $picasa;

    /**
     * DefaultController constructor.
     *
     * @param \Google_Client   $client
     * @param RouterInterface  $router
     * @param SessionInterface $session
     * @param Picasa           $picasa
     */
    public function __construct(
        \Google_Client $client,
        RouterInterface $router,
        SessionInterface $session,
        Picasa $picasa
    ) {
        $client->setRedirectUri(
            $router->generate('login', [], RouterInterface::ABSOLUTE_URL)
        );

        $picasa->setGoogleClient($client);
        $picasa->setSession($session);

        $this->googleClient = $client;
        $this->router = $router;
        $this->session = $session;
        $this->picasa = $picasa;
    }

    public function index(Request $request)
    {
        $hasJs = !empty($request->query->get('js'));

        try {
            $token = $this->picasa->getOrRefreshAccessToken();
        } catch (GoogleTokenRefreshException $exception) {
            $token = null;
        }

        if (!$token) {
            return $this->redirectToRoute('login');
        }

        $photos = [];

        $selectedYear = $request->query->get('year');
        if ($selectedYear) {
            $albumLink = $this->picasa->getAlbumLinkByName('Auto Backup');

            if (!$albumLink) {
                $this->addFlash('error', 'Auto Backup album not found');

                return $this->logout();
            }

            $day = \date('m-d');
            foreach (\range(\date('Y') - 1, $selectedYear) as $year) {
                $key = $year.'-'.$day;
                $yearPhotos = $this->picasa->getPhotosByDateFromAlbum(
                    $albumLink, new \DateTime($key)
                );
                if (\count($yearPhotos)) {
                    $photos[$key] = $yearPhotos;
                }
            }
        }

        return $this->render(
            'default/index.html.twig', [
                'selectedYear' => $selectedYear,
                'photos' => $photos,
                'hasJs' => (int) $hasJs,
            ]
        );
    }

    public function login(Request $request)
    {
        $token = $this->picasa->fetchAccessToken($request);
        if ($token) {
            return $this->redirectToRoute('index');
        }

        return $this->render(
            'default/login.html.twig', [
                'authUrl' => $this->googleClient->createAuthUrl(),
            ]
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function logout()
    {
        $this->session->remove(Picasa::TOKEN_KEY);

        return $this->redirectToRoute('login');
    }
}
