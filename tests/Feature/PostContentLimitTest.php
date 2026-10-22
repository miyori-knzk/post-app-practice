<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostContentLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $category = Category::create(['name' => '雑記']);
        $this->post = Post::create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function update(string $content, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)->put(route('posts.update', $this->post), [
            'title' => '新しいタイトル',
            'category_id' => $this->post->category_id,
            'content' => $content,
        ]);
    }

    public function test_140字ちょうどの本文は更新できる(): void
    {
        $content = str_repeat('あ', 140);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame($content, $this->post->fresh()->content);
    }

    public function test_141字の本文は日本語のエラーになり保存されない(): void
    {
        $this->update(str_repeat('あ', 141))
            ->assertSessionHasErrors(['content' => '本文は140字以内で入力してください。']);

        $this->assertSame('元の本文', $this->post->fresh()->content);
    }

    public function test_改行を1字として数える(): void
    {
        $content = str_repeat('あ', 69)."\r\n".str_repeat('い', 70);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame(str_repeat('あ', 69)."\n".str_repeat('い', 70), $this->post->fresh()->content);
    }

    public function test_本文が空なら日本語のエラーになる(): void
    {
        $this->update('')
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);
    }

    public function test_編集画面に残り字数が表示される(): void
    {
        $this->actingAs($this->user)
            ->get(route('posts.edit', $this->post))
            ->assertOk()
            ->assertSee('id="content-counter"', false)
            ->assertSee('残り 136 字');
    }

    public function test_超過して更新に失敗した画面では超過字数が表示される(): void
    {
        $this->actingAs($this->user)
            ->from(route('posts.edit', $this->post))
            ->followingRedirects()
            ->put(route('posts.update', $this->post), [
                'title' => '新しいタイトル',
                'category_id' => $this->post->category_id,
                'content' => str_repeat('あ', 143),
            ])
            ->assertSee('本文は140字以内で入力してください。')
            ->assertSee('140字を超えています（あと3字減らしてください）');
    }

    public function test_他人の投稿は更新できない(): void
    {
        $this->update('乗っ取り', User::factory()->create())->assertForbidden();

        $this->assertSame('元の本文', $this->post->fresh()->content);
    }
}
