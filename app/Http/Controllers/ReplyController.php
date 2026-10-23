<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reply;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    public function store(Request $request, Post $post)
    {
        // ブラウザは改行を \r\n で送るが、字数は改行を1字と数えるので揃える
        if (is_string($request->input('content'))) {
            $request->merge(['content' => str_replace("\r\n", "\n", $request->input('content'))]);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:'.Post::CONTENT_MAX_LENGTH],
        ], [
            'content.required' => '本文を入力してください。',
            'content.max' => '本文は'.Post::CONTENT_MAX_LENGTH.'字以内で入力してください。',
        ]);

        $post->replies()->create($validated + ['user_id' => $request->user()->id]);

        return redirect()->route('posts.show', $post);
    }

    public function destroy(Reply $reply)
    {
        $this->authorize('delete', $reply);

        $reply->delete();

        return redirect()->route('posts.show', $reply->post_id);
    }
}
