<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'category'])->latest()->get();

        return view('posts.index', compact('posts'));
    }

    public function show(Post $post)
    {
        $post->load(['user', 'category']);
        $replies = $post->replies()->with('user')->oldest()->orderBy('id')->get();

        return view('posts.show', compact('post', 'replies'));
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        $categories = Category::orderBy('id')->get();

        return view('posts.edit', compact('post', 'categories'));
    }

    public function create()
    {
        $categories = Category::orderBy('id')->get();

        return view('posts.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->user()->posts()->create($this->validatedPost($request));

        return redirect()->route('posts.index');
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $post->update($this->validatedPost($request));

        return redirect()->route('posts.index');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('posts.index');
    }

    private function validatedPost(Request $request): array
    {
        // ブラウザは改行を \r\n で送るが、画面の字数カウントは改行を1字と数えるので揃える
        if (is_string($request->input('content'))) {
            $request->merge(['content' => str_replace("\r\n", "\n", $request->input('content'))]);
        }

        return $request->validate([
            'title' => 'required|string|max:255',
            'content' => ['required', 'string', 'max:'.Post::CONTENT_MAX_LENGTH],
            'category_id' => 'required|exists:categories,id',
        ], [
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは255字以内で入力してください。',
            'content.required' => '本文を入力してください。',
            'content.max' => '本文は'.Post::CONTENT_MAX_LENGTH.'字以内で入力してください。',
            'category_id.required' => 'トピックを選択してください。',
        ]);
    }
}
