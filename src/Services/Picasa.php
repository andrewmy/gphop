<?php

namespace App\Services;

use App\Exception\GoogleTokenRefreshException;
use App\ValueObject\Photo;
use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Picasa
{
    const TOKEN_KEY = 'googleToken';

    /** @var SessionInterface */
    protected $session;

    /** @var \Google_Client */
    protected $googleClient;

    /** @var CacheInterface */
    protected $cache;

    public function __construct()
    {
        $this->cache = new FilesystemCache();
    }

    /**
     * @param SessionInterface $session
     *
     * @return self
     */
    public function setSession(SessionInterface $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @param \Google_Client $googleClient
     *
     * @return self
     */
    public function setGoogleClient(\Google_Client $googleClient): self
    {
        $this->googleClient = $googleClient;

        return $this;
    }

    /**
     * @throws GoogleTokenRefreshException
     *
     * @return array|null
     */
    public function getOrRefreshAccessToken()
    {
        $token = $this->session->get(self::TOKEN_KEY);
        if ($token) {
            $this->googleClient->setAccessToken($token);
            if ($this->googleClient->isAccessTokenExpired()) {
                try {
                    $token = $this->googleClient->refreshToken(
                        $this->googleClient->getRefreshToken()
                    );
                } catch (\Exception $exception) {
                    throw new GoogleTokenRefreshException();
                }
            }
        }

        return $token;
    }

    /**
     * @param Request $request
     *
     * @return array|null
     */
    public function fetchAccessToken(Request $request)
    {
        $token = null;
        $code = $request->query->get('code');
        if ($code) {
            try {
                $token = $this->googleClient->fetchAccessTokenWithAuthCode($code);
            } catch (\Exception $exception) {
            }
            $this->session->set(self::TOKEN_KEY, $token);
        }

        return $token;
    }

    /**
     * @param string $name
     *
     * @return null|string
     */
    public function getAlbumLinkByName(string $name)
    {
        /** @var Client $httpClient */
        $httpClient = $this->googleClient->authorize();

        $response = $httpClient->get(
            'https://picasaweb.google.com/data/feed/api/user/default'
        );
        $xml = \simplexml_load_string($response->getBody());
        $albumLink = null;
        foreach ($xml->entry as $album) {
            if ($album->title->__toString() === $name) {
                foreach ($album->link as $link) {
                    $attr = $link->attributes();
                    if (
                        $attr->rel->__toString() === 'http://schemas.google.com/g/2005#feed'
                    ) {
                        $albumLink = $attr->href->__toString();
                    }
                }
                break;
            }
        }

        return $albumLink;
    }

    /**
     * @param string    $albumLink
     * @param \DateTime $date
     *
     * @return Photo[]
     */
    public function getPhotosByDateFromAlbum(string $albumLink, \DateTime $date)
    {
        $cacheKey = \str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':'],
            '',
            $albumLink.'###'.$date->format('Y-m-d')
        );

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $photos = [];

        /** @var Client $client */
        $client = $this->googleClient->authorize();
        $response = $client->get(
            $albumLink.'?'.\http_build_query(['q' => $date->format('Y-m-d')])
        );
        $xml = \simplexml_load_string($response->getBody());
        if (\count($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $entryLink = '';
                foreach ($entry->link as $link) {
                    $attr = $link->attributes();
                    if ($attr->rel->__toString() === 'alternate') {
                        $entryLink = $attr->href->__toString();
                    }
                }
                $photos[] = (new Photo())
                    ->setId($entry->id->__toString())
                    ->setTitle($entry->title->__toString())
                    ->setImage($entry->content->attributes()->src->__toString())
                    ->setLink($entryLink)
                    ->setDatetime(new \DateTime(
                        $entry->published->__toString()
                    ));
            }
        }

        $this->cache->set($cacheKey, $photos);

        return $photos;
    }
}
