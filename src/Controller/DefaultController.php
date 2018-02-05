<?php

namespace App\Controller;

use App\Exception\GoogleTokenRefreshException;
use App\Services\Picasa;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    /**
     * @return array|null|RedirectResponse
     */
    protected function getToken()
    {
        try {
            $token = $this->picasa->getOrRefreshAccessToken();
        } catch (GoogleTokenRefreshException $exception) {
            $token = null;
        }

        if (!$token) {
            return $this->redirectToRoute('login');
        }

        return $token;
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function index(Request $request)
    {
        $hasJs = !empty($request->query->get('js'));

        $token = $this->getToken();
        if ($token instanceof RedirectResponse) {
            return $token;
        }

        $photos = [];

        $selectedYear = $request->get('year');
        if ($selectedYear) {
            $albumLink = $this->picasa->getAlbumLinkByName('Auto Backup');

            if (!$albumLink) {
                $this->addFlash('error', 'Auto Backup album not found');

                return $this->logout();
            }

            if (!$hasJs) {
                $dates = $this->picasa->getDateRange($selectedYear);
                foreach ($dates as $year) {
                    $yearPhotos = $this->picasa->getPhotosByDateFromAlbum(
                        $albumLink, new \DateTime($year)
                    );
                    if (\count($yearPhotos)) {
                        $photos[$year] = $yearPhotos;
                    }
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

    /**
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
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
        $this->picasa->forgetToken();

        return $this->redirectToRoute('login');
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function dates(Request $request)
    {
        $token = $this->getToken();
        if ($token instanceof RedirectResponse) {
            return $this->json([
                'error' => true, 'redirect' => $token->getTargetUrl()
            ]);
        }

        $result = [
            'error' => false, 'message' => null, 'list' => [], 'mainLink' => null,
        ];
        $selectedYear = $request->get('year');

        if ($selectedYear) {
            $albumLink = $this->picasa->getAlbumLinkByName('Auto Backup');

            if (!$albumLink) {
                $result['error'] = true;
                $result['message'] = 'Auto Backup album not found';

                return $this->json($result);
            }

            $result['mainLink'] = $albumLink;
            $result['list'] = $this->picasa->getDateRange($selectedYear);
        }

        return $this->json($result);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function datePhotos(Request $request)
    {
        $token = $this->getToken();
        if ($token instanceof RedirectResponse) {
            return $this->json([
                'error' => true, 'redirect' => $token->getTargetUrl()
            ]);
        }

        $result = ['error' => false, 'message' => null, 'list' => []];
        $date = $request->get('date');
        $mainLink = $request->get('mainLink');

        if ($date) {
            try {
                $result['list'] = $this->picasa->getPhotosByDateFromAlbum(
                    $mainLink,
                    new \DateTime($date)
                );
            } catch (\Exception $e) {
                $result['error'] = true;
                $result['message'] = 'Loading failed';
            }
        }

        return $this->json($result);
    }
}
