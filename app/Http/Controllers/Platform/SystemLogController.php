<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Recent application errors for platform admins on hosting without shell access.
 */
class SystemLogController extends Controller
{
    private const MAX_BYTES = 512 * 1024;

    public function __invoke(): View
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0] ?? null;

        return view('platform.logs', [
            'file' => $file ? basename($file) : null,
            'entries' => $file ? $this->entries($file) : [],
        ]);
    }

    /**
     * @return list<array{time: string, level: string, message: string, detail: string}>
     */
    private function entries(string $path): array
    {
        $size = filesize($path) ?: 0;
        $handle = fopen($path, 'r');
        fseek($handle, max(0, $size - self::MAX_BYTES));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+\-]+)\] \w+\.(\w+): (.*?)(?=^\[\d{4}-\d{2}-\d{2}[ T]|\z)/ms', $content, $matches, PREG_SET_ORDER);

        $entries = array_map(function (array $match): array {
            $lines = explode("\n", trim($match[3]));

            return [
                'time' => $match[1],
                'level' => strtolower($match[2]),
                'message' => mb_strimwidth(array_shift($lines), 0, 600, '…'),
                'detail' => implode("\n", array_slice($lines, 0, 25)),
            ];
        }, $matches);

        return array_slice(array_reverse($entries), 0, 50);
    }
}
