<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Service\PokeApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class FavoriteController extends AbstractController
{
    #[Route('/favorites', name: 'favorites')]
    public function index(EntityManagerInterface $entityManager, SessionInterface $session, PokeApiClient $pokeApiClient): Response
    {
        $sessionId = $session->getId();
        $favorites = $entityManager->getRepository(Favorite::class)->findBySessionId($sessionId);
        $types = $pokeApiClient->getTypes();

        $pokemon = [];
        foreach ($favorites as $favorite) {
            $details = $pokeApiClient->getPokemon($favorite->getPokemonName());
            $pokemon[] = [
                'name' => $details['name'],
                'image' => $details['sprites']['other']['official-artwork']['front_default'] ?? null,
                'id' => $details['id'],
                'favoriteId' => $favorite->getId()
            ];
        }

        return $this->render('pokemon/index.html.twig', [
            'pokemon' => $pokemon,
            'types' => $types['results'],
        ]);
    }

    #[Route('/favorites/add/{name}', name: 'favorites_add')]
    public function add(EntityManagerInterface $entityManager, SessionInterface $session, string $name): Response
    {
        $sessionId = $session->getId();

        // confirm the pokemon is not saved
        $existing = $entityManager->getRepository(Favorite::class)->findOneBy([
            'sessionId' => $sessionId,
            'pokemonName' => $name
        ]);

        
        if (!$existing) {
            $favorite = new Favorite();
            $favorite->setSessionId($sessionId);
            $favorite->setPokemonName($name);
            $entityManager->persist($favorite);
            $entityManager->flush();
        }
        
        return $this->redirectToRoute('pokemon_show', ['name' => $name]);
    }

    #[Route('/favorites/remove/{id}', name: 'favorites_remove')]
    public function delete(EntityManagerInterface $entityManager, int $id, Request $request): Response
    {
        $favorite = $entityManager->getRepository(Favorite::class)->find($id);

        if ($favorite) {
            $pokemonName = $favorite->getPokemonName();
            $entityManager->remove($favorite);
            $entityManager->flush();
        }

        // Redirect back to the referer (Pokemon detail page) or favorites list
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('favorites');
    }    
}
