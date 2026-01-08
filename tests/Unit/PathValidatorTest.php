<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Models\WebApp;
use App\Services\FileManager\PathValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PathValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team;
    protected Server $server;
    protected WebApp $webApp;
    protected PathValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
        $this->server = Server::factory()->create([
            'team_id' => $this->team->id,
            'status' => Server::STATUS_ACTIVE,
        ]);
        $this->webApp = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'example.com',
            'root_path' => '/home/sitekit/web/example.com',
        ]);
        $this->validator = new PathValidator();
    }

    public function test_allows_path_within_web_app_root(): void
    {
        $this->assertTrue(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/current/app')
        );
    }

    public function test_allows_web_app_root_path(): void
    {
        $this->assertTrue(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com')
        );
    }

    public function test_denies_path_outside_web_app_root(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/other-domain.com')
        );
    }

    public function test_denies_system_paths(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/etc/passwd')
        );
    }

    public function test_denies_directory_traversal(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/../other-domain.com')
        );
    }

    public function test_denies_git_directory(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/.git/config')
        );
    }

    public function test_denies_ssh_directory(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/.ssh/id_rsa')
        );
    }

    public function test_denies_env_file(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/.env')
        );
    }

    public function test_denies_env_backup_file(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/.env.backup')
        );
    }

    public function test_denies_node_modules_directory(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/node_modules/package')
        );
    }

    public function test_denies_vendor_directory(): void
    {
        $this->assertFalse(
            $this->validator->isAllowed($this->webApp, '/home/sitekit/web/example.com/vendor/autoload.php')
        );
    }

    public function test_php_files_are_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/file.php'));
    }

    public function test_javascript_files_are_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/file.js'));
        $this->assertTrue($this->validator->isEditable('/path/to/file.ts'));
        $this->assertTrue($this->validator->isEditable('/path/to/file.jsx'));
        $this->assertTrue($this->validator->isEditable('/path/to/file.tsx'));
    }

    public function test_vue_files_are_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/Component.vue'));
    }

    public function test_css_files_are_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/style.css'));
        $this->assertTrue($this->validator->isEditable('/path/to/style.scss'));
    }

    public function test_json_files_are_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/package.json'));
    }

    public function test_markdown_files_are_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/README.md'));
    }

    public function test_image_files_are_not_editable(): void
    {
        $this->assertFalse($this->validator->isEditable('/path/to/image.jpg'));
        $this->assertFalse($this->validator->isEditable('/path/to/image.png'));
        $this->assertFalse($this->validator->isEditable('/path/to/image.gif'));
    }

    public function test_binary_files_are_not_editable(): void
    {
        $this->assertFalse($this->validator->isEditable('/path/to/file.exe'));
        $this->assertFalse($this->validator->isEditable('/path/to/archive.zip'));
    }

    public function test_gitignore_is_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/.gitignore'));
    }

    public function test_dockerfile_is_editable(): void
    {
        $this->assertTrue($this->validator->isEditable('/path/to/Dockerfile'));
    }

    public function test_small_files_are_size_editable(): void
    {
        $this->assertTrue($this->validator->isSizeEditable(1024)); // 1KB
        $this->assertTrue($this->validator->isSizeEditable(1024 * 1024)); // 1MB
    }

    public function test_large_files_are_not_size_editable(): void
    {
        $this->assertFalse($this->validator->isSizeEditable(10 * 1024 * 1024)); // 10MB
    }

    public function test_valid_filename(): void
    {
        $this->assertTrue($this->validator->isValidFilename('myfile.txt'));
        $this->assertTrue($this->validator->isValidFilename('my-file.txt'));
        $this->assertTrue($this->validator->isValidFilename('my_file.txt'));
    }

    public function test_invalid_filename_with_path_separator(): void
    {
        $this->assertFalse($this->validator->isValidFilename('path/to/file.txt'));
        $this->assertFalse($this->validator->isValidFilename('path\\to\\file.txt'));
    }

    public function test_invalid_filename_dot(): void
    {
        $this->assertFalse($this->validator->isValidFilename('.'));
        $this->assertFalse($this->validator->isValidFilename('..'));
    }

    public function test_invalid_filename_empty(): void
    {
        $this->assertFalse($this->validator->isValidFilename(''));
        $this->assertFalse($this->validator->isValidFilename('   '));
    }

    public function test_protected_file_as_filename(): void
    {
        $this->assertFalse($this->validator->isValidFilename('.env'));
        $this->assertFalse($this->validator->isValidFilename('.env.backup'));
    }

    public function test_valid_directory_name(): void
    {
        $this->assertTrue($this->validator->isValidDirectoryName('my-folder'));
        $this->assertTrue($this->validator->isValidDirectoryName('src'));
    }

    public function test_invalid_directory_name_protected(): void
    {
        $this->assertFalse($this->validator->isValidDirectoryName('.git'));
        $this->assertFalse($this->validator->isValidDirectoryName('node_modules'));
        $this->assertFalse($this->validator->isValidDirectoryName('vendor'));
    }

    public function test_get_allowed_base_path(): void
    {
        $this->assertEquals(
            '/home/sitekit/web/example.com',
            $this->validator->getAllowedBasePath($this->webApp)
        );
    }

    public function test_get_protected_files_list(): void
    {
        $protectedFiles = $this->validator->getProtectedFiles();
        $this->assertContains('.env', $protectedFiles);
        $this->assertContains('.env.backup', $protectedFiles);
    }

    public function test_get_protected_directories_list(): void
    {
        $protectedDirs = $this->validator->getProtectedDirectories();
        $this->assertContains('.git', $protectedDirs);
        $this->assertContains('node_modules', $protectedDirs);
        $this->assertContains('vendor', $protectedDirs);
    }
}
