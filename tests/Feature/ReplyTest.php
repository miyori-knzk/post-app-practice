<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $other;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => 'ポスト主']);
        $this->other = User::factory()->create(['name' => '返信する人']);
        $category = Category::create(['name' => '雑記']);
        $this->post = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function reply(string $content, ?User $by = null, ?Post $to = null): Reply
    {
        return Reply::create([
            'user_id' => ($by ?? $this->other)->id,
            'post_id' => ($to ?? $this->post)->id,
            'content' => $content,
        ]);
    }

    private function send(?string $content, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->other)
            ->from(route('posts.show', $this->post))
            ->post(route('replies.store', $this->post), ['content' => $content]);
    }

    public function test_詳細画面にポストとリプライが表示される(): void
    {
        $this->reply('最初のリプライ');

        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('元のタイトル')
            ->assertSee('元の本文')
            ->assertSee('最初のリプライ')
            ->assertSee('返信する人');
    }

    public function test_リプライがないポストでは案内が表示される(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('まだリプライがありません。');
    }

    public function test_リプライは古い順に並ぶ(): void
    {
        $this->reply('二番目')->forceFill(['created_at' => now()->subMinute()])->save();
        $this->reply('一番目')->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->reply('三番目');

        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertSeeInOrder(['一番目', '二番目', '三番目']);
    }

    public function test_他のポストのリプライは表示されない(): void
    {
        $another = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->post->category_id,
            'title' => '別のポスト',
            'content' => '別の本文',
        ]);
        $this->reply('別ポストへの返信', null, $another);

        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertDontSee('別ポストへの返信');
    }

    public function test_存在しないポストの詳細は404(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.show', 9999))
            ->assertNotFound();
    }

    public function test_リプライを送ると保存され詳細画面に表示される(): void
    {
        $this->send('はじめまして')->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseHas('replies', [
            'user_id' => $this->other->id,
            'post_id' => $this->post->id,
            'content' => 'はじめまして',
        ]);

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSee('はじめまして');
    }

    public function test_140字ちょうどのリプライは送れる(): void
    {
        $content = str_repeat('あ', 140);

        $this->send($content)->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseHas('replies', ['content' => $content]);
    }

    public function test_141字のリプライはエラーになり増えない(): void
    {
        $this->send(str_repeat('あ', 141))
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors(['content' => '本文は140字以内で入力してください。']);

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_改行を1字として数える(): void
    {
        $content = str_repeat('あ', 69)."\r\n".str_repeat('い', 70);

        $this->send($content)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replies', ['content' => str_repeat('あ', 69)."\n".str_repeat('い', 70)]);
    }

    /**
     * @dataProvider emptyContents
     */
    public function test_本文が空ならエラーになり増えない(?string $content): void
    {
        $this->send($content)
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);

        $this->assertDatabaseCount('replies', 0);
    }

    public static function emptyContents(): array
    {
        return [
            '未入力' => [null],
            '空文字' => [''],
            '空白のみ' => ['   '],
        ];
    }

    public function test_エラーで戻った画面にエラーと入力が表示される(): void
    {
        $content = str_repeat('あ', 141);

        $this->actingAs($this->other)
            ->from(route('posts.show', $this->post))
            ->followingRedirects()
            ->post(route('replies.store', $this->post), ['content' => $content])
            ->assertSee('本文は140字以内で入力してください。')
            ->assertSee($content);
    }

    public function test_自分のリプライは削除できる(): void
    {
        $reply = $this->reply('消したい');

        $this->actingAs($this->other)
            ->delete(route('replies.destroy', $reply))
            ->assertRedirect(route('posts.show', $this->post));

        $this->assertModelMissing($reply);
    }

    public function test_他人のリプライは削除できない(): void
    {
        $reply = $this->reply('消されたくない');

        $this->actingAs($this->author)
            ->delete(route('replies.destroy', $reply))
            ->assertForbidden();

        $this->assertModelExists($reply);
    }

    public function test_削除ボタンは自分のリプライにだけ表示される(): void
    {
        $mine = $this->reply('私のリプライ', $this->other);
        $theirs = $this->reply('あの人のリプライ', $this->author);

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSee(route('replies.destroy', $mine))
            ->assertDontSee(route('replies.destroy', $theirs));
    }

    public function test_ポストを削除するとリプライも消える(): void
    {
        $another = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->post->category_id,
            'title' => '別のポスト',
            'content' => '別の本文',
        ]);
        $gone = $this->reply('消える');
        $stays = $this->reply('残る', null, $another);

        $this->actingAs($this->author)
            ->delete(route('posts.destroy', $this->post))
            ->assertRedirect(route('posts.index'));

        $this->assertModelMissing($gone);
        $this->assertModelExists($stays);
    }

    public function test_未ログインでは詳細も送信も削除もできない(): void
    {
        $reply = $this->reply('リプライ');

        $this->get(route('posts.show', $this->post))->assertRedirect(route('login'));
        $this->post(route('replies.store', $this->post), ['content' => 'x'])->assertRedirect(route('login'));
        $this->delete(route('replies.destroy', $reply))->assertRedirect(route('login'));

        $this->assertDatabaseCount('replies', 1);
    }

    public function test_一覧の各ポストに返信リンクが表示される(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('<a href="'.route('posts.show', $this->post).'">返信</a>', false)
            ->assertDontSee(route('posts.edit', $this->post));
    }
}
