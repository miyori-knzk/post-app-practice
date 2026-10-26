<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::create(['name' => '雑記']);
    }

    private function store(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)->post(route('posts.store'), array_merge([
            'title' => '新しいタイトル',
            'category_id' => $this->category->id,
            'content' => '新しい本文',
        ], $overrides));
    }

    public function test_作成画面にタイトル_トピック_本文の入力欄が出る(): void
    {
        $this->actingAs($this->user)
            ->get(route('posts.create'))
            ->assertOk()
            ->assertSee('name="title"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="content"', false)
            ->assertSee('選んでください')
            ->assertSee('残り 140 字')
            // required のブラウザ検証で送信が止まると、画面にエラーが出ないため
            ->assertSee('novalidate', false);
    }

    public function test_タイムラインに_ポストを書く_リンクが出る(): void
    {
        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('href="'.route('posts.create').'"', false)
            ->assertSee('ポストを書く');
    }

    public function test_送信するとポストが増えてタイムラインに戻る(): void
    {
        $this->store()->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseHas('posts', [
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => '新しいタイトル',
            'content' => '新しい本文',
        ]);
    }

    public function test_増えたポストはタイムラインの先頭に出る(): void
    {
        Post::unguarded(fn () => Post::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'title' => '古いポスト',
            'content' => '古い本文',
            'created_at' => now()->subDay(),
        ]));

        $this->store();

        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertSeeInOrder(['新しいタイトル', '古いポスト']);
    }

    public function test_増えたポストには自分の名前が出て編集と削除ができる(): void
    {
        $this->store();
        $post = Post::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertSee($this->user->name)
            ->assertSee('href="'.route('posts.edit', $post).'"', false)
            ->assertSee('action="'.route('posts.destroy', $post).'"', false);

        $this->actingAs($this->user)->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'));
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_user_idを偽っても投稿者はログインユーザーになる(): void
    {
        $other = User::factory()->create();

        $this->store(['user_id' => $other->id]);

        $this->assertSame($this->user->id, Post::firstOrFail()->user_id);
    }

    public function test_本文が空ならエラーになりポストは増えない(): void
    {
        foreach (['', '   ', null] as $empty) {
            $this->store(['content' => $empty])
                ->assertSessionHasErrors(['content' => '本文を入力してください。']);
        }

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_エラーで戻ったとき入力した内容が残りエラーが表示される(): void
    {
        $this->actingAs($this->user)
            ->from(route('posts.create'))
            ->followingRedirects()
            ->post(route('posts.store'), [
                'title' => '残るタイトル',
                'category_id' => $this->category->id,
                'content' => '',
            ])
            ->assertSee('本文を入力してください。')
            ->assertSee('value="残るタイトル"', false)
            ->assertSee('value="'.$this->category->id.'" selected', false);
    }

    public function test_140字ちょうどは保存され141字はエラーになる(): void
    {
        $this->store(['content' => str_repeat('あ', 141)])
            ->assertSessionHasErrors(['content' => '本文は140字以内で入力してください。']);
        $this->assertDatabaseCount('posts', 0);

        $this->store(['content' => str_repeat('あ', 140)])->assertRedirect(route('posts.index'));
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_改行を1字として数える(): void
    {
        $content = str_repeat('あ', 69)."\r\n".str_repeat('い', 70);

        $this->store(['content' => $content])->assertRedirect(route('posts.index'));

        $this->assertSame(str_repeat('あ', 69)."\n".str_repeat('い', 70), Post::firstOrFail()->content);
    }

    public function test_タイトル未入力は日本語のエラーになる(): void
    {
        $this->store(['title' => ''])
            ->assertSessionHasErrors(['title' => 'タイトルを入力してください。']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_256字のタイトルは日本語のエラーになる(): void
    {
        $this->store(['title' => str_repeat('あ', 256)])
            ->assertSessionHasErrors(['title' => 'タイトルは255字以内で入力してください。']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_トピック未選択はエラーになる(): void
    {
        $this->store(['category_id' => ''])
            ->assertSessionHasErrors(['category_id' => 'トピックを選択してください。']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_存在しないトピックはエラーになる(): void
    {
        $this->store(['category_id' => 9999])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_他人のポストには編集と削除が出ない(): void
    {
        $this->store();
        $post = Post::firstOrFail();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('posts.index'))
            ->assertDontSee('href="'.route('posts.edit', $post).'"', false)
            ->assertDontSee('action="'.route('posts.destroy', $post).'"', false);
    }

    public function test_未ログインでは作成画面にも送信にも入れない(): void
    {
        $this->get(route('posts.create'))->assertRedirect(route('login'));
        $this->post(route('posts.store'), [
            'title' => 'タイトル',
            'category_id' => $this->category->id,
            'content' => '本文',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('posts', 0);
    }
}
