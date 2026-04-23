<?php
namespace app\controllers;

use app\libraries\routers\WebRouter;
use app\libraries\response\JsonResponse;
use app\libraries\core\Controller;
use app\libraries\core\Request;

class ConfigEditorController extends Controller {
    // Context constants
    const CONTEXT_GRADEABLE_CONFIG = 'GRADEABLE_CONFIG';
    const CONTEXT_REDACTIONS = 'REDACTIONS';
    // Add more as needed

    /**
     * Entry point for all config editor file operations
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse {
        $context = $request->getRequestVar('context');
        $action = $request->getRequestVar('action');
        $path = $request->getRequestVar('path');
        // Validate context and path strictly here
        // Route to appropriate handler
        switch ($action) {
            case 'list':
                return $this->listFiles($context, $path);
            case 'load':
                return $this->loadFile($context, $path);
            case 'save':
                return $this->saveFile($context, $path, $request->getRequestVar('content'));
            case 'add_folder':
                return $this->addFolder($context, $path);
            case 'add_file':
                return $this->addFile($context, $path, $request->files['file'] ?? null);
            case 'delete':
                return $this->deleteFileOrFolder($context, $path);
            default:
                return JsonResponse::error('Unknown action');
        }
    }

    // Helper: get base directory for a context
    private function getBaseDirForContext(string $context): ?string {
        switch ($context) {
            case self::CONTEXT_GRADEABLE_CONFIG:
                // Example: return /var/local/submitty/courses/semester/course/gradeable_config
                // You must adjust this to your actual config root
                return '/var/local/submitty/gradeable_config';
            case self::CONTEXT_REDACTIONS:
                return '/var/local/submitty/redactions';
            default:
                return null;
        }
    }

    // Helper: validate that $full_path is inside $base_dir
    private function isValidPath(string $full_path, string $base_dir): bool {
        return strpos(realpath($full_path), realpath($base_dir)) === 0;
    }

    private function listFiles(string $context, string $path): JsonResponse {
        $base_dir = $this->getBaseDirForContext($context);
        if ($base_dir === null) {
            return JsonResponse::error('Invalid context');
        }
        $dir = realpath($base_dir . '/' . ltrim($path, '/'));
        if ($dir === false || !$this->isValidPath($dir, $base_dir) || !is_dir($dir)) {
            return JsonResponse::error('Invalid path');
        }
        $files = array_values(array_filter(scandir($dir), function($f) { return $f !== '.' && $f !== '..'; }));
        $result = [];
        foreach ($files as $f) {
            $full = $dir . '/' . $f;
            $result[] = [
                'name' => $f,
                'is_dir' => is_dir($full),
                'size' => is_file($full) ? filesize($full) : null,
                'mtime' => filemtime($full)
            ];
        }
        return JsonResponse::success(['files' => $result]);
    }

    private function loadFile(string $context, string $path): JsonResponse {
        $base_dir = $this->getBaseDirForContext($context);
        if ($base_dir === null) {
            return JsonResponse::error('Invalid context');
        }
        $file = realpath($base_dir . '/' . ltrim($path, '/'));
        if ($file === false || !$this->isValidPath($file, $base_dir) || !is_file($file)) {
            return JsonResponse::error('Invalid file path');
        }
        $content = file_get_contents($file);
        return JsonResponse::success(['content' => $content]);
    }

    private function saveFile(string $context, string $path, $content): JsonResponse {
        $base_dir = $this->getBaseDirForContext($context);
        if ($base_dir === null) {
            return JsonResponse::error('Invalid context');
        }
        $file = $base_dir . '/' . ltrim($path, '/');
        $real_dir = realpath(dirname($file));
        if ($real_dir === false || !$this->isValidPath($real_dir, $base_dir)) {
            return JsonResponse::error('Invalid file path');
        }
        if (file_put_contents($file, $content) === false) {
            return JsonResponse::error('Failed to save file');
        }
        return JsonResponse::success();
    }

    private function addFolder(string $context, string $path): JsonResponse {
        $base_dir = $this->getBaseDirForContext($context);
        if ($base_dir === null) {
            return JsonResponse::error('Invalid context');
        }
        $dir = $base_dir . '/' . ltrim($path, '/');
        $real_parent = realpath(dirname($dir));
        if ($real_parent === false || !$this->isValidPath($real_parent, $base_dir)) {
            return JsonResponse::error('Invalid parent directory');
        }
        if (is_dir($dir)) {
            return JsonResponse::error('Folder already exists');
        }
        if (!mkdir($dir, 0770, true)) {
            return JsonResponse::error('Failed to create folder');
        }
        return JsonResponse::success();
    }

    private function addFile(string $context, string $path, $file): JsonResponse {
        $base_dir = $this->getBaseDirForContext($context);
        if ($base_dir === null) {
            return JsonResponse::error('Invalid context');
        }
        $dest = $base_dir . '/' . ltrim($path, '/');
        $real_dir = realpath(dirname($dest));
        if ($real_dir === false || !$this->isValidPath($real_dir, $base_dir)) {
            return JsonResponse::error('Invalid destination directory');
        }
        if (!isset($file) || !is_uploaded_file($file['tmp_name'])) {
            return JsonResponse::error('No file uploaded');
        }
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return JsonResponse::error('Failed to upload file');
        }
        return JsonResponse::success();
    }

    private function deleteFileOrFolder(string $context, string $path): JsonResponse {
        $base_dir = $this->getBaseDirForContext($context);
        if ($base_dir === null) {
            return JsonResponse::error('Invalid context');
        }
        $target = realpath($base_dir . '/' . ltrim($path, '/'));
        if ($target === false || !$this->isValidPath($target, $base_dir)) {
            return JsonResponse::error('Invalid path');
        }
        if (is_dir($target)) {
            // Recursively delete directory
            $it = new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($target);
        } else if (is_file($target)) {
            unlink($target);
        } else {
            return JsonResponse::error('Target not found');
        }
        return JsonResponse::success();
    }
}
