<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contracts\AIException;
use App\AI\Contracts\AIResult;

/**
 * ResponseParser — chuẩn hoá response provider → AIResult thống nhất.
 *
 * KHÔNG gọi API. KHÔNG đọc DB. Chỉ nhận AIResult thô từ provider và:
 *  - kiểm tra tính hợp lệ
 *  - trích xuất content / files
 *  - đảm bảo metadata (provider, model, capability, module) đầy đủ
 *
 * Business module KHÔNG bao giờ nhận raw API response — chỉ nhận AIResult.
 */
final class ResponseParser
{
    /**
     * @param array<string, mixed> $context
     */
    public function parse(AIResult $result, array $context = []): AIResult
    {
        if (!$result->isOk()) {
            return $result;
        }

        $result->capability ??= $context['capability'] ?? null;
        $result->module ??= $context['module'] ?? null;
        $result->provider ??= $context['provider'] ?? null;
        $result->model ??= $context['model'] ?? null;

        if (!is_array($result->files)) {
            $result->files = [];
        }

        $result->files = $this->normalizeFiles($result->files);
        $result->status ??= 'completed';

        // LƯU Ý: $result->raw vẫn được giữ nội bộ để trích operation id,
        // nhưng KHÔNG bao giờ được trả ra business layer.
        // AIResult::toArray() đã loại bỏ trường 'raw'.

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFiles(array $files): array
    {
        $out = [];

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $entry = [];
            foreach (['url', 'kind', 'mime_type', 'b64_json', 'outer_url'] as $key) {
                if (array_key_exists($key, $file)) {
                    $entry[$key] = $file[$key];
                }
            }

            if ($entry !== []) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Trích xuất URL media từ kết quả (dùng cho AssetManager).
     *
     * @return array<int, string>
     */
    public function extractUrls(AIResult $result): array
    {
        $urls = [];

        foreach ($result->files as $file) {
            if (is_array($file) && !empty($file['url']) && is_string($file['url'])) {
                $urls[] = $file['url'];
            }
        }

        return $urls;
    }

    /**
     * Chuẩn hoá kết quả lỗi để ghi log / activity (đã lọc secret).
     *
     * @return array<string, mixed>
     */
    public function parseError(AIResult $result): array
    {
        return [
            'type'    => $result->errorType() ?? AIException::UNKNOWN,
            'message' => $result->errorMessage(),
            'context' => $result->error ?? [],
        ];
    }
}
