<?php

namespace tests\app\libraries;

use app\libraries\FileUtils;
use app\libraries\NotebookUtils;
use tests\BaseUnitTest;

class NotebookUtilsTester extends BaseUnitTest {
    private string $notebook_dir;
    private string $tmp_path;

    public function setUp(): void {
        $this->notebook_dir = FileUtils::joinPaths(__TEST_DATA__, 'notebooks');
        $this->tmp_path = FileUtils::joinPaths(sys_get_temp_dir(), 'notebook_utils_test_' . uniqid());
    }

    public function tearDown(): void {
        if (is_dir($this->tmp_path)) {
            FileUtils::recursiveRmdir($this->tmp_path);
        }
    }

    private function notebookPath(string $filename): string {
        return FileUtils::joinPaths($this->notebook_dir, $filename);
    }

    public function testMarkdownAndCodeCellsAreParsed(): void {
        $result = NotebookUtils::jupyterToSubmittyNotebook($this->notebookPath('basic.ipynb'));

        $this->assertFalse($result['size_exceeded']);
        $this->assertSame(0, $result['skipped_content_count']);
        $this->assertSame(0, $result['skipped_output_count']);

        $this->assertCount(3, $result['cells']);

        $this->assertSame(
            [
                'type' => 'markdown',
                'markdown_data' => "# Title\nSome **markdown** text.",
            ],
            $result['cells'][0]
        );

        $this->assertSame(
            [
                'type' => 'short_answer',
                'label' => '',
                'programming_language' => 'python',
                'initial_value' => "print('hello world')",
                'rows' => 1,
                'filename' => 'cell-1',
                'recent_submission' => '',
                'version_submission' => '',
                'codemirror_mode' => 'ipython',
            ],
            $result['cells'][1]
        );

        $this->assertSame(
            [
                'type' => 'output',
                'output_text' => "hello world\n",
            ],
            $result['cells'][2]
        );
    }

    public function testEmptyCellsAreSkipped(): void {
        $result = NotebookUtils::jupyterToSubmittyNotebook($this->notebookPath('empty_cells.ipynb'));

        $this->assertFalse($result['size_exceeded']);
        $this->assertSame(0, $result['skipped_content_count']);
        $this->assertSame(0, $result['skipped_output_count']);

        $this->assertCount(1, $result['cells']);
        $this->assertSame(
            [
                'type' => 'markdown',
                'markdown_data' => 'Visible content',
            ],
            $result['cells'][0]
        );
    }

    public function testMarkdownAttachments(): void {
        $result = NotebookUtils::jupyterToSubmittyNotebook($this->notebookPath('markdown_attachment.ipynb'));

        $this->assertSame(1, $result['skipped_content_count']);
        $this->assertCount(1, $result['cells']);

        $expected_markdown =
            "![img](data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=)\n"
            . "![doc](Image skipped: image type not supported.)";

        $this->assertSame('markdown', $result['cells'][0]['type']);
        $this->assertSame($expected_markdown, $result['cells'][0]['markdown_data']);
    }

    public function testCodeCellDisplayDataImageOutput(): void {
        $result = NotebookUtils::jupyterToSubmittyNotebook($this->notebookPath('code_output_image.ipynb'));

        $this->assertSame(0, $result['skipped_output_count']);
        $this->assertCount(2, $result['cells']);

        $this->assertSame('short_answer', $result['cells'][0]['type']);
        $this->assertSame('plt.show()', $result['cells'][0]['initial_value']);
        $this->assertSame('img-cell', $result['cells'][0]['filename']);

        $image_cell = $result['cells'][1];
        $this->assertSame('image', $image_cell['type']);
        $this->assertSame(
            'data:image/png;base64, iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            $image_cell['image']
        );
        $this->assertSame('<Figure size 100x100>', $image_cell['alt_text']);
        $this->assertSame(0, $image_cell['width']);
        $this->assertSame(0, $image_cell['height']);
    }

    public function testSizeExceededSkipsProcessing(): void {
        FileUtils::createDir($this->tmp_path);
        $big_file = FileUtils::joinPaths($this->tmp_path, 'huge.ipynb');

        $handle = fopen($big_file, 'w');
        ftruncate($handle, (1024 * 1024 * 10) + 1);
        fclose($handle);

        $result = NotebookUtils::jupyterToSubmittyNotebook($big_file);

        $this->assertTrue($result['size_exceeded']);
        $this->assertSame([], $result['cells']);
        $this->assertSame(0, $result['skipped_content_count']);
        $this->assertSame(0, $result['skipped_output_count']);
    }

    public function testTruncateTextShortTextIsUnchanged(): void {
        $notebook_utils = new NotebookUtils();
        $result = $this->invokeMethod($notebook_utils, 'truncateText', 'short text');

        $this->assertSame('short text', $result['text']);
        $this->assertSame(0, $result['was_truncated']);
    }

    public function testTruncateTextOverSizeLimitIsTruncated(): void {
        $notebook_utils = new NotebookUtils();
        $long_text = str_repeat('a', (1024 * 5) + 500);

        $result = $this->invokeMethod($notebook_utils, 'truncateText', $long_text);

        $this->assertSame(1, $result['was_truncated']);
        $this->assertStringContainsString('[Output truncated', $result['text']);
        $this->assertLessThan(strlen($long_text), strlen($result['text']));
    }

    public function testTruncateTextOverLineLimitIsTruncated(): void {
        $notebook_utils = new NotebookUtils();
        $lines = array_fill(0, 300, 'line');
        $long_text = implode("\n", $lines);

        $result = $this->invokeMethod($notebook_utils, 'truncateText', $long_text);

        $this->assertSame(1, $result['was_truncated']);
        $this->assertStringContainsString('[Output truncated', $result['text']);
    }
}
