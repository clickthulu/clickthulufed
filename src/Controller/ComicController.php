<?php

namespace App\Controller;

use App\Entity\Comic;
use App\Entity\Page;
use App\Entity\User;
use App\Enumerations\MediaPathEnumeration;
use App\Exceptions\SettingNotFoundException;
use App\Form\AddPageType;
use App\Form\CreateComicType;
use App\Form\EditComicType;
use App\Helpers\SettingsHelper;
use App\Service\Settings;
use App\Traits\BooleanTrait;
use App\Traits\ComicOwnerTrait;
use App\Traits\MediaPathTrait;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ComicController extends AbstractController
{
    use ComicOwnerTrait;
    use BooleanTrait;
    use MediaPathTrait;

    /**
     * Generates a form for creating comics
     *
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param Settings $settings
     * @return Response
     */
    #[Route('/comic/create', name: 'app_comiccreate')]
    public function createComicForm(EntityManagerInterface $entityManager, Request $request, Settings $settings): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $comic = new Comic();
        $form = $this->createForm(CreateComicType::class, $comic);
        $form->handleRequest($request);
        /**
         * @var User $user;
         */
        $user = $this->getUser();
        try {
            $requireApproval = $settings->get()['require_comic_approval'];
        } catch (SettingNotFoundException) {
            $requireApproval = false;
        }
        try {
            if ($form->isSubmitted() && $form->isValid()) {

                $comic
                    ->setIsactive(!$requireApproval)
                    ->setOwner($user);

                $entityManager->persist($comic);
                $entityManager->flush();
                return new RedirectResponse($this->generateUrl('app_profile'));
            }
        } catch (\Exception $e){
            $err = new FormError($e->getMessage());
            $form->addError($err);
        }

        return $this->render('comic/createcomic.html.twig', [
            'createcomicForm' => $form->createView()
        ]);
    }


    /**
     * Checks the current comic and user database to see if an identifier is already in use.  If the id already exists, it throws an error
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    #[Route('/comic/checkslug/{slug}', name: 'app_comiccheckslug')]
    public function checkComicSlugForUniqueness($slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $users = $entityManager->getRepository(User::class)->findBy(['username' => $slug]);
        $comics = $entityManager->getRepository(Comic::class)->findBy(['slug'=> $slug]);
        $passfail = (count($users) + count($comics));

        if ($passfail === 0) {
            return new Response("Unique Identifier is available");
        }
        return new Response("Unique Identifier is currently in use", 400);
    }


    #[Route('/comic/{slug}/addpage', name: 'app_addpage')]
    #[Route('/comic/{slug}/editpage/{pageid?}', name: 'app_editpage')]
    public function addEditComicPage(string $slug, ?int $pageid, EntityManagerInterface $entityManager, Request $request)
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug] );
        /**
         * @var Page $page
         */
        $page = new Page();
        $page->setComic($comic);
        $page->setPublishdate($page->calculateNextPublishDate());



        if (!empty($pageid)) {
            /**
             * @var Page $page
             */
            $page = $entityManager->getRepository(Page::class)->findOneBy(['id' => $pageid]);
        }


        $form = $this->createForm(AddPageType::class, $page);
        $form->handleRequest($request);

        try {
            if ($form->isSubmitted() && $form->isValid()) {
                $page->setUploadedby($user);
                $entityManager->persist($page);
                $entityManager->flush();
                return new RedirectResponse($this->generateUrl('app_comicmanagepages', ['slug' => $comic->getSlug()]));
            }
        } catch (\Exception $e){
            $err = new FormError($e->getMessage());
            $form->addError($err);
        }

        return $this->render(
            'comic/addedit_page.html.twig',
            [
                'comic'=>$comic,
                'page' => $page,
                'addPageForm' => $form->createView()
            ]
        );

    }

    #[Route('/comic/{slug}/uploadpage', name: 'app_comicuploadpages')]
    public function uploadComicPages(string $slug, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic;
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug]);


        if (!$this->comicUserMatch($user, $comic)) {
            return new JsonResponse(['status' => 'failed', 'message' => "You do not have permission to upload images for this comic"], 400);
        }

        $settings = SettingsHelper::init($entityManager);

        $currentMax = ini_get('upload_max_filesize');
        $maxUpload = $settings->get('upload_max_filesize', $currentMax);
        ini_set('upload_max_filesize', $maxUpload);

        $comicpath = $this->getMediaPath($settings, $user, $comic, MediaPathEnumeration::PATH_COMIC);



        $files = array_pop($_FILES);
        if (empty($files)) {
            throw new FileNotFoundException("No file was uploaded.");
        }

        move_uploaded_file($files['tmp_name'], "{$comicpath}/{$files['name']}");

        $generateThumbnails = $settings->get('generate_thumbnails', true);

        if ($generateThumbnails) {
            $thumbpath = $this->getMediaPath($settings, $user, $comic, MediaPathEnumeration::PATH_THUMBNAIL);

            $source = imagecreatefromstring(file_get_contents("{$comicpath}/{$files['name']}"));
            $sourcesize = getimagesize("{$comicpath}/{$files['name']}");

            $sourceX = $sourcesize[0];
            $sourceY = $sourcesize[1];

            $newX = 200;
            $newY = $sourceY * $newX/$sourceX;

            $target = imagecreatetruecolor($newX, $newY);
            imagecopyresized($target, $source, 0,0, 0, 0, $newX, $newY, $sourceX, $sourceY);
            imagepng($target, "{$thumbpath}/{$files['name']}");

        }


        return new JsonResponse([
            'status' => 'uploaded',
            'comic' => $comic->getSlug(),
            'file' => $files['name']
        ]);


    }


    #[Route('/comic/{slug}/pages', name: 'app_comicmanagepages')]
    public function manageComicPages(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug]);

        if (!$this->comicUserMatch($user, $comic)) {
            $this->addFlash("error", "You do not have permission to manage this comic");
        }

        return $this->render('comic/pagelist.html.twig', [
            'comic' => $comic,
        ]);
    }


    #[Route('/comic/{slug}/activate', name: 'app_comicactivate')]
    public function activateComic(string $slug, Request $request, EntityManagerInterface $entityManager, Settings $settings): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug]);
        $hasPerm = $this->hasPermissions($user, $comic);
        $needsApproval = $settings->get()['require_comic_approval'];
        $canActivate = false;
        if (in_array("ROLE_OWNER", $user->getRoles()) || in_array("ROLE_ADMIN", $user->getRoles())) {
            $canActivate = true;
        } elseif ($hasPerm && !$this->toBool($needsApproval)) {
            $canActivate = true;
        }

        if ($canActivate) {
            $comic->setIsactive(true);
            $comic->setActivatedon(new DateTime());
            $entityManager->persist($comic);
            $entityManager->flush();
            return new RedirectResponse($this->generateUrl("app_profile"));
        }

        $this->addFlash('error', 'You do not have the permission to activate this comic.  Please see an administrator.');
        return new RedirectResponse($this->generateUrl("app_profile"), 400);
    }

    #[Route('/comic/{slug}/deactivate', name: 'app_comicdeactivate')]
    public function deactivateComic(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug]);
        $hasPerm = $this->hasPermissions($user, $comic);

        if ($hasPerm) {
            $comic->setIsactive(false);
            $entityManager->persist($comic);
            $entityManager->flush();
            return new RedirectResponse($this->generateUrl("app_profile"));
        }

        $this->addFlash('error', 'You do not have the permission to deactivate this comic.  Please see an administrator.');
        return new RedirectResponse($this->generateUrl("app_profile"), 400);

    }



    #[Route('/comic/{slug}/delete', name: 'app_comicdelete')]
    public function deleteComic(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug]);
        $canDelete = $this->hasPermissions($user, $comic);

        if (!$canDelete) {
            $this->addFlash('error', "You do not have permission to delete {$comic->getName()}");
            return new RedirectResponse($this->generateUrl("app_profile"), 400);
        }

        $comic->setIsdeleted(true);
        $comic->setDeletedon(new DateTime());
        $entityManager->persist($comic);
        $entityManager->flush();
        return new RedirectResponse($this->generateUrl("app_profile"));

    }





    #[Route('/comic/{slug}/edit', name: 'app_editcomic')]
    public function editComic(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /**
         * @var User $user
         */
        $user = $this->getUser();
        /**
         * @var Comic $comic
         */
        $comic = $entityManager->getRepository(Comic::class)->findOneBy(['slug' => $slug]);
        $hasPerm = $this->hasPermissions($user, $comic);

        if (!$hasPerm) {
            $this->addFlash('error', "You do not have permissions to edit {$comic->getName()}");
            return new RedirectResponse($this->generateUrl("app_profile"), 400);
        }

        $form = $this->createForm(EditComicType::class, $comic);
        $form->handleRequest($request);

        try {
            if ($form->isSubmitted() && $form->isValid()) {

                $entityManager->persist($comic);
                $entityManager->flush();
                return new RedirectResponse($this->generateUrl('app_profile'));
            }
        } catch (\Exception $e){
            $err = new FormError($e->getMessage());
            $form->addError($err);
        }

        return $this->render('comic/editcomic.html.twig', [
            'comic' => $comic,
            'editcomicForm' => $form->createView()
        ]);
    }



}