<?php

namespace App\Services;

use RuntimeException;

/**
 * Finds the node executable.
 *
 * Never rely on PATH for this. A queue worker, php-fpm, or a backgrounded
 * `artisan serve` frequently has a narrower PATH than your shell — under nvm it
 * almost never has node at all — and the failure is a bare exit code 127 with
 * no output, which is miserable to diagnose.
 */
class NodeBinary
{
    private static ?string $resolved = null;

    public static function path(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        // 1. Explicitly configured — the answer for any real deployment.
        $configured = config('ai.node_binary');
        if ($configured && is_executable($configured)) {
            return self::$resolved = $configured;
        }

        // 2. Whatever the shell would use.
        $which = trim((string) @shell_exec('command -v node 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return self::$resolved = $which;
        }

        // 3. The usual places, including the newest nvm install.
        $candidates = glob(getenv('HOME').'/.nvm/versions/node/*/bin/node') ?: [];
        rsort($candidates);

        foreach ([...$candidates, '/opt/homebrew/bin/node', '/usr/local/bin/node', '/usr/bin/node'] as $candidate) {
            if (is_executable($candidate)) {
                return self::$resolved = $candidate;
            }
        }

        throw new RuntimeException(
            'Could not find node on this machine. Set NODE_BINARY in .env to its full path — '.
            "on this machine `which node` will tell you what to use."
        );
    }
}
