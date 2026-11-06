<?php

namespace app\libraries;

/**
 * Helpers for matching image annotation files to the submitted images they belong to.
 *
 * Annotations are stored in annotations/{gradeable}/{submitter}/{active_version}/ as
 * "{md5(key)}_{grader_id}.json", where the key is the image's path relative to its
 * version directory, prefixed with the top level directory (e.g. "submissions/folder/img.png").
 * Older annotations were named using the md5 of the full (real or anonymous) path instead.
 */
class ImageAnnotationUtils {
    /** Top level directories whose images can be annotated */
    const ANNOTATABLE_DIRS = ['submissions', 'submissions_processed'];

    /**
     * Get the annotation key for an image, or null if the image cannot be annotated.
     * @param string $real_path Path to the image, containing the real submitter id
     * @param string $course_path
     * @param string $gradeable_id
     * @param string $submitter_id User or team id that owns the image
     * @return string|null
     */
    public static function getAnnotationKey(string $real_path, string $course_path, string $gradeable_id, string $submitter_id): ?string {
        if (!FileUtils::isInSubmitterDirectory($real_path, $course_path, $gradeable_id, $submitter_id)) {
            return null;
        }
        $resolved_path = realpath($real_path);
        foreach (self::ANNOTATABLE_DIRS as $dir) {
            $base = realpath(FileUtils::joinPaths($course_path, $dir, $gradeable_id, $submitter_id));
            if ($base === false || !str_starts_with($resolved_path, $base . DIRECTORY_SEPARATOR)) {
                continue;
            }
            // The first path section is the submission version
            $relative_parts = explode(DIRECTORY_SEPARATOR, substr($resolved_path, strlen($base) + 1));
            array_shift($relative_parts);
            if (count($relative_parts) === 0) {
                return null;
            }
            return $dir . '/' . implode('/', $relative_parts);
        }
        return null;
    }

    /**
     * Get the path of the annotation file for an image and grader
     */
    public static function getAnnotationFilePath(string $annotation_dir, string $key, string $grader_id): string {
        return FileUtils::joinPaths($annotation_dir, md5($key) . '_' . $grader_id . '.json');
    }

    /**
     * Load annotation JSON data for an image from the annotation directory
     * @param string $annotation_dir The directory containing annotation files
     * @param string $key The image's annotation key
     * @param string[] $legacy_paths Full paths that older annotation files for this image may be named by
     * @return array<string, string> Array of annotation JSONs keyed by grader_id
     */
    public static function loadAnnotationJsons(string $annotation_dir, string $key, array $legacy_paths = []): array {
        $annotation_jsons = [];
        $legacy_hashes = array_map('md5', $legacy_paths);
        $key_hash = md5($key);

        if (is_dir($annotation_dir)) {
            $dir_iter = new \FilesystemIterator($annotation_dir);
            foreach ($dir_iter as $annotation_file) {
                $file_hash = explode('_', $annotation_file->getFilename())[0];
                $is_legacy = in_array($file_hash, $legacy_hashes, true);
                if ($file_hash !== $key_hash && !$is_legacy) {
                    continue;
                }
                $annotation_decoded = json_decode(file_get_contents($annotation_file->getPathname()), true);
                if (!is_array($annotation_decoded) || !isset($annotation_decoded['grader_id'])) {
                    continue;
                }
                $grader_id = $annotation_decoded['grader_id'];
                // Annotations saved under the current key take priority over legacy ones
                if ($is_legacy && isset($annotation_jsons[$grader_id])) {
                    continue;
                }
                $annotation_jsons[$grader_id] = json_encode($annotation_decoded['annotations']);
            }
        }

        return $annotation_jsons;
    }

    /**
     * Remove a grader's legacy annotation files for an image
     * @param string[] $legacy_paths
     */
    public static function removeLegacyAnnotationFiles(string $annotation_dir, array $legacy_paths, string $grader_id): void {
        foreach (array_unique($legacy_paths) as $legacy_path) {
            $legacy_file = FileUtils::joinPaths($annotation_dir, md5($legacy_path) . '_' . $grader_id . '.json');
            if (is_file($legacy_file)) {
                unlink($legacy_file);
            }
        }
    }
}
