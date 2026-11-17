<?php

declare(strict_types=1);

namespace App\Todo\Presentation\Controller;

use App\Todo\Domain\Exception\TodoRefusedException;
use App\Todo\Domain\Service\TodoListDomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The todo list's one page. Every command is a native POST form: carried out, it answers 303 to the list; refused,
 * it answers 422 with the list, the reason and the form as sent.
 */
final class TodoController extends AbstractController
{
    public function __construct(
        private readonly TodoListDomainService $todoList
    ) {
    }

    #[Route(path: '/', name: 'todo.presentation.list', methods: ['GET'])]
    public function listAction(): Response
    {
        return $this->showList();
    }

    #[Route(path: '/items', name: 'todo.presentation.add_item', methods: ['POST'])]
    public function addItemAction(Request $request): Response
    {
        $text = $request->request->getString('text');

        try {
            $this->todoList->addItem($text);
        } catch (TodoRefusedException $refused) {
            return $this->showList($refused->getMessage(), $text, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('todo.presentation.list', [], Response::HTTP_SEE_OTHER);
    }

    #[Route(
        path: '/items/{itemId}/done',
        name: 'todo.presentation.mark_item_done',
        requirements: ['itemId' => Requirement::UUID],
        methods: ['POST']
    )]
    public function markItemDoneAction(string $itemId): Response
    {
        try {
            $this->todoList->markItemDone($itemId);
        } catch (TodoRefusedException $refused) {
            return $this->showList($refused->getMessage(), '', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('todo.presentation.list', [], Response::HTTP_SEE_OTHER);
    }

    #[Route(
        path: '/items/{itemId}/delete',
        name: 'todo.presentation.delete_item',
        requirements: ['itemId' => Requirement::UUID],
        methods: ['POST']
    )]
    public function deleteItemAction(string $itemId): Response
    {
        try {
            $this->todoList->deleteItem($itemId);
        } catch (TodoRefusedException $refused) {
            return $this->showList($refused->getMessage(), '', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('todo.presentation.list', [], Response::HTTP_SEE_OTHER);
    }

    private function showList(?string $refusal = null, string $text = '', int $status = Response::HTTP_OK): Response
    {
        return $this->render(
            '@todo.presentation/todo_list.html.twig',
            [
                'items'         => $this->todoList->items(),
                'refusal'       => $refusal,
                'text'          => $text,
                'maxTextLength' => TodoListDomainService::MAX_TEXT_LENGTH,
            ],
            new Response('', $status)
        );
    }
}
