<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MyMusic\Models\GoogleToken;
use App\Modules\MyMusic\Models\Playlist;
use App\Modules\MyMusic\Models\PlaylistItem;
use App\Modules\MyMusic\Models\QuotaEntry;
use App\Modules\MyMusic\Models\Track;
use App\Modules\MyMusic\Models\Video;
use App\Modules\MyMusic\Support\TitleParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyMusicTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config(['music.google.client_id' => 'test-id', 'music.google.client_secret' => 'test-secret']);
    }

    protected function connect(): GoogleToken
    {
        return GoogleToken::create([
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'account_name' => 'Test Channel',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('music.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_lists_the_music_module_card(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Music');
    }

    public function test_index_shows_connect_card_when_not_connected(): void
    {
        $this->actingAs($this->user)
            ->get(route('music.index'))
            ->assertOk()
            ->assertSee(route('music.oauth.redirect'));
    }

    public function test_index_renders_library_when_connected(): void
    {
        $this->connect();
        Video::create([
            'video_id' => 'abc123', 'raw_title' => 'Dua Lipa - Levitating (Official Video)',
            'channel_title' => 'Dua Lipa', 'liked_position' => 0, 'fetched_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('music.index'))
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_sync_inserts_new_videos_and_logs_quota(): void
    {
        $this->connect();
        Http::fake([
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [[
                    'snippet' => [
                        'title' => 'Artist - Song (Official Video)',
                        'videoOwnerChannelTitle' => 'ArtistVEVO',
                        'position' => 0,
                        'publishedAt' => '2026-01-01T00:00:00Z',
                        'thumbnails' => ['medium' => ['url' => 'https://img.example/t.jpg']],
                    ],
                    'contentDetails' => ['videoId' => 'vid00000001', 'videoPublishedAt' => '2025-05-05T00:00:00Z'],
                ]],
            ]),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('music.sync'))
            ->assertOk()
            ->assertJson(['inserted' => 1, 'done' => true, 'total' => 1]);

        $this->assertDatabaseHas('music_videos', ['video_id' => 'vid00000001', 'channel_title' => 'ArtistVEVO']);
        $this->assertSame(1, QuotaEntry::todayUnits());

        // Re-sync: same item is refreshed, not duplicated.
        $this->actingAs($this->user)->postJson(route('music.sync'))->assertJson(['inserted' => 0, 'total' => 1]);
    }

    public function test_sync_surfaces_quota_exceeded(): void
    {
        $this->connect();
        Http::fake([
            'https://www.googleapis.com/youtube/v3/*' => Http::response([
                'error' => ['errors' => [['reason' => 'quotaExceeded']]],
            ], 403),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('music.sync'))
            ->assertStatus(422)
            ->assertJson(['quotaExceeded' => true]);
    }

    public function test_title_parser_handles_common_shapes(): void
    {
        $cases = [
            ['Dua Lipa - Levitating (Official Music Video)', null, 'Dua Lipa', 'Levitating', true],
            ['Arctic Monkeys – Do I Wanna Know? [Official Video]', null, 'Arctic Monkeys', 'Do I Wanna Know?', true],
            ['Bohemian Rhapsody (Queen)', 'SomeUser', 'Queen', 'Bohemian Rhapsody', true],
            ['INNA - Hot | Official Video', 'INNA', 'INNA', 'Hot', true],
            ['Blinding Lights', 'The Weeknd - Topic', 'The Weeknd', 'Blinding Lights', true],
            ['How to fix a bike chain', 'DIY Garage', 'DIY Garage', 'How to fix a bike chain', false],
            ['Deleted video', null, null, 'Deleted video', false],
        ];

        foreach ($cases as [$raw, $channel, $artist, $title, $isMusic]) {
            $p = TitleParser::parse($raw, $channel);
            $this->assertSame($artist, $p['artist'], "artist of: {$raw}");
            $this->assertSame($title, $p['title'], "title of: {$raw}");
            $this->assertSame($isMusic, $p['is_music'], "is_music of: {$raw}");
        }
    }

    public function test_parser_does_not_corrupt_trailing_emoji(): void
    {
        $p = TitleParser::parse('아티스트 - 곡명⚽️🏻', 'Some - Topic');
        $this->assertTrue(mb_check_encoding($p['title'], 'UTF-8'));
        $this->assertSame('곡명⚽️🏻', $p['title']);
    }

    public function test_parser_survives_invalid_utf8(): void
    {
        $p = TitleParser::parse("Artist - Song \xB4 Live", 'SomeChannel');
        $this->assertSame('Artist', $p['artist']);
        $this->assertTrue($p['is_music']);
    }

    public function test_feat_is_moved_from_artist_to_title(): void
    {
        $p = TitleParser::parse('The Weeknd ft. Daft Punk - Starboy (Official Video) [HD]', 'TheWeekndVEVO');
        $this->assertSame('The Weeknd', $p['artist']);
        $this->assertSame('Starboy (feat. Daft Punk)', $p['title']);
    }

    public function test_manual_track_edit_sets_manual_status(): void
    {
        $this->connect();
        Video::create(['video_id' => 'v1', 'raw_title' => 'x - y', 'fetched_at' => now()]);
        $track = Track::create(['video_id' => 'v1', 'title' => 'y', 'artist' => 'x', 'enrich_status' => 'ok']);

        $this->actingAs($this->user)
            ->putJson(route('music.tracks.update', $track), [
                'artist' => 'Real Artist', 'title' => 'Real Title', 'album' => 'Album',
                'year' => 1999, 'genres' => 'rock, indie',
            ])
            ->assertOk();

        $track->refresh();
        $this->assertSame('manual', $track->enrich_status);
        $this->assertSame(['rock', 'indie'], $track->genres);
        $this->assertSame(1999, $track->year);
    }

    public function test_toggle_music_parks_and_revives_track(): void
    {
        $this->connect();
        $video = Video::create(['video_id' => 'v2', 'raw_title' => 'x - y', 'fetched_at' => now()]);
        $track = Track::create(['video_id' => 'v2', 'title' => 'y', 'enrich_status' => 'pending']);

        $this->actingAs($this->user)->postJson(route('music.videos.toggle-music', $video))->assertOk();
        $this->assertFalse($video->refresh()->is_music);
        $this->assertSame('skipped', $track->refresh()->enrich_status);

        $this->actingAs($this->user)->postJson(route('music.videos.toggle-music', $video))->assertOk();
        $this->assertSame('pending', $track->refresh()->enrich_status);
    }

    public function test_playlist_create_process_and_resume_after_quota(): void
    {
        $this->connect();

        // First process call: playlists.insert + first item OK, second item hits quota.
        Http::fakeSequence('https://www.googleapis.com/youtube/v3/playlists*')
            ->push(['id' => 'PLxyz']);
        Http::fakeSequence('https://www.googleapis.com/youtube/v3/playlistItems*')
            ->push(['id' => 'item1'])
            ->push(['error' => ['errors' => [['reason' => 'quotaExceeded']]]], 403)
            ->push(['id' => 'item2'])
            ->push(['id' => 'item3']);

        $create = $this->actingAs($this->user)
            ->postJson(route('music.playlists.store'), [
                'name' => 'Chill 90s',
                'filter' => ['genres' => ['rock'], 'mus' => 'music'],
                'videoIds' => ['v1', 'v2', 'v3'],
            ])
            ->assertOk()
            ->json('playlist');

        $this->assertSame(3, $create['queued']);

        // Chunk 1 → creates the playlist, inserts v1, pauses on v2.
        $this->actingAs($this->user)
            ->postJson(route('music.playlists.process', $create['id']))
            ->assertStatus(422)
            ->assertJson(['quotaExceeded' => true])
            ->assertJsonPath('playlist.status', 'quota_paused')
            ->assertJsonPath('playlist.inserted', 1)
            ->assertJsonPath('playlist.queued', 2);

        // Resume ("tomorrow") → inserts v2 + v3, playlist ready.
        $this->actingAs($this->user)
            ->postJson(route('music.playlists.process', $create['id']))
            ->assertOk()
            ->assertJsonPath('playlist.status', 'ready')
            ->assertJsonPath('playlist.inserted', 3)
            ->assertJsonPath('playlist.queued', 0);

        $this->assertDatabaseHas('music_playlists', ['youtube_id' => 'PLxyz', 'status' => 'ready']);
        $this->assertSame(3, PlaylistItem::where('playlist_id', $create['id'])->count());
        // Quota log: 1 playlists.insert (50) + 4 playlistItems.insert attempts (200).
        $this->assertSame(250, QuotaEntry::todayUnits());
    }

    public function test_playlist_sync_queues_only_missing_videos(): void
    {
        $this->connect();
        $playlist = Playlist::create(['name' => 'P', 'youtube_id' => 'PL1', 'status' => 'ready', 'queue' => []]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => 'v1', 'position' => 0]);

        $this->actingAs($this->user)
            ->postJson(route('music.playlists.sync', $playlist), ['videoIds' => ['v1', 'v2', 'v3']])
            ->assertOk()
            ->assertJson(['added' => 2])
            ->assertJsonPath('playlist.queued', 2);

        $this->assertSame(['v2', 'v3'], $playlist->fresh()->queue);
    }

    public function test_playlists_page_renders(): void
    {
        $this->connect();
        Playlist::create(['name' => 'Road trip', 'youtube_id' => 'PL9', 'status' => 'ready', 'queue' => []]);

        $this->actingAs($this->user)
            ->get(route('music.playlists.index'))
            ->assertOk()
            ->assertSee('Road trip')
            ->assertSee('music.youtube.com/playlist?list=PL9');
    }

    public function test_not_embeddable_flag_is_persisted(): void
    {
        $this->connect();
        $video = Video::create(['video_id' => 'v3', 'raw_title' => 'x - y', 'fetched_at' => now()]);

        $this->actingAs($this->user)->postJson(route('music.videos.not-embeddable', $video))->assertOk();
        $this->assertFalse($video->refresh()->embeddable);
    }
}
