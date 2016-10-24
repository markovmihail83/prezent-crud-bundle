<?php

namespace Prezent\CrudBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Prezent\CrudBundle\Model\Configuration;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base crud controller
 *
 * @author Sander Marechal
 */
abstract class CrudController extends Controller
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * List objects
     *
     * @Route("/")
     */
    public function indexAction(Request $request)
    {
        $configuration = $this->getConfiguration();

        $sortField = $request->get('sort_by', $configuration->getDefaultSortField());
        $sortOrder = $request->get('sort_order', $configuration->getDefaultSortOrder());

        // Ensure that the correct field is active
        $request->query->set('sort_by', $sortField);
        $request->query->set('sort_order', $sortOrder);

        $queryBuilder = $this->getRepository()->createQueryBuilder('o');
        $queryBuilder->addOrderBy('o.' . $sortField, $sortOrder);

        $this->configureListCriteria($request, $queryBuilder);

        $pager = new Pagerfanta(new DoctrineORMAdapter($queryBuilder));
        $pager->setCurrentPage($request->get('page', 1));

        $grid = $this->get('grid_factory')->createGrid(
            $configuration->getGridType(),
            $configuration->getGridOptions()
        );

        return $this->render($this->getTemplate($request, 'index'), [
            'config' => $configuration,
            'grid'   => $grid->createView(),
            'pager'  => $pager,
        ]);
    }

    /**
     * Add a new object
     *
     * @Route("/add")
     */
    public function addAction(Request $request)
    {
        $configuration = $this->getConfiguration();
        $om = $this->getObjectManager();

        $form = $this->createForm(
            $configuration->getFormType(),
            $this->newInstance($request),
            $configuration->getFormOptions()
        );

        $form->handleRequest($request);

        if ($form->isValid()) {
            $om->persist($form->getData());

            try {
                $om->flush();
                $this->addFlash('success', sprintf('flash.%s.add.success', $configuration->getName()));
            } catch (\Exception $e) {
                $this->addFlash('error', sprintf('flash.%s.add.error', $configuration->getName()));
            }

            return $this->redirectToRoute($configuration->getRoutePrefix() . 'index');
        }

        return $this->render($this->getTemplate($request, 'add'), [
            'config' => $configuration,
            'form'   => $form->createView(),
        ]);
    }

    /**
     * Edit an object
     *
     * @Route("/edit/{id}")
     */
    public function editAction(Request $request, $id)
    {
        $configuration = $this->getConfiguration();
        $om = $this->getObjectManager();

        $form = $this->createForm(
            $configuration->getFormType(),
            $this->findObject($id),
            $configuration->getFormOptions()
        );

        $form->handleRequest($request);

        if ($form->isValid()) {
            $om->persist($form->getData());

            try {
                $om->flush();
                $this->addFlash('success', sprintf('flash.%s.edit.success', $configuration->getName()));
            } catch (\Exception $e) {
                $this->addFlash('error', sprintf('flash.%s.edit.error', $configuration->getName()));
            }

            return $this->redirectToRoute($configuration->getRoutePrefix() . 'index');
        }

        return $this->render($this->getTemplate($request, 'edit'), [
            'config' => $configuration,
            'form'   => $form->createView(),
        ]);
    }

    /**
     * Delete an object
     *
     * @Route("/delete/{id}")
     */
    public function deleteAction($id)
    {
        $configuration = $this->getConfiguration();
        $object = $this->findObject($id);

        $om = $this->getObjectManager();
        $om->remove($object);

        try {
            $em->flush();
            $this->addFlash('success', sprintf('flash.%s.delete.success', $configuration->getName()));
        } catch (\Exception $e) {
            $this->addFlash('error', sprintf('flash.%s.delete.error', $configuration->getName()));
        }

        return $this->redirectToRoute($configuration->getRoutePrefix() . 'index');
    }

    /**
     * Set the configuration
     *
     * @param Configuration $config
     * @return void
     */
    protected function configure(Configuration $config)
    {
    }

    /**
     * Get the configuration
     *
     * @return Configuration
     */
    protected function getConfiguration()
    {
        if (!$this->configuration) {
            $this->configuration = new Configuration($this);
            $this->configure($this->configuration);
            $this->configuration->validate();
        }

        return $this->configuration;
    }

    /**
     * Generate new entity instance
     *
     * @param Request $request
     * @return object
     */
    protected function newInstance(Request $request)
    {
        return null;
    }

    /**
     * Find an object by ID
     *
     * @param mixed $id
     * @return object
     * @throws NotFoundException
     */
    protected function findObject($id)
    {
        if (!($object = $this->getRepository()->find($id))) {
            throw $this->createNotFoundException(
                sprintf('Object %s(%s) not found', $this->getConfiguration()->getEntityClass(), $id)
            );
        }

        return $object;
    }

    /**
     * Configure list criteria
     *
     * @param Request $request
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    protected function configureListCriteria(Request $request, QueryBuilder $queryBuilder)
    {
    }

    /**
     * Get the template for an action
     *
     * @param string $action
     * @return string
     */
    protected function getTemplate(Request $request, $action)
    {
        $templates = $this->get('prezent_crud.template_guesser')->guessTemplateNames([$this, $action], $request);

        foreach ($templates as $template) {
            if ($this->get('templating')->exists($template)) {
                return $template;
            }
        }

        return array_shift($templates); // This ensures a proper error message about a missing template
    }

    /**
     * Get the object manager for the configured class
     *
     * @return ObjectManager
     */
    protected function getObjectManager($class = null)
    {
        return $this->getDoctrine()->getManagerForClass($class ?: $this->getConfiguration()->getEntityClass());
    }

    /**
     * Get the repository for the configured class
     *
     * @return ObjectRepository
     */
    protected function getRepository($class = null)
    {
        return $this->getObjectManager($class)->getRepository($class ?: $this->getConfiguration()->getEntityClass());
    }
}
