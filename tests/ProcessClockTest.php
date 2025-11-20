<?php

use PHPUnit\Framework\TestCase;

class ProcessClockTest extends TestCase
{
    private const TARGET_FILE = __DIR__ . '/../modules/attendance/process_clock.php';

    public static function setUpBeforeClass(): void
    {
        self::loadEuclideanDistance();
    }

    public function testEuclideanDistanceReturnsZeroForMatchingVectors(): void
    {
        $vector = [0.1, 0.2, 0.3];

        $distance = euclideanDistance($vector, $vector);

        $this->assertSame(0.0, $distance);
    }

    public function testEuclideanDistanceReturnsPositiveForDistinctVectors(): void
    {
        $distance = euclideanDistance([0.1, 0.2, 0.3], [0.3, 0.2, 0.1]);

        $this->assertGreaterThan(0.0, $distance);
    }

    public function testEuclideanDistanceHandlesDifferingLengths(): void
    {
        $distance = euclideanDistance([0.1, 0.2], [0.1, 0.2, 0.3]);

        $this->assertSame(PHP_FLOAT_MAX, $distance);
    }

    private static function loadEuclideanDistance(): void
    {
        if (function_exists('euclideanDistance')) {
            return;
        }

        $functionBody = self::extractFunctionBody('euclideanDistance');

        if ($functionBody === '') {
            self::fail('Unable to locate euclideanDistance in process_clock.php');
        }

        eval($functionBody);
    }

    private static function extractFunctionBody(string $functionName): string
    {
        $source = file_get_contents(self::TARGET_FILE);

        $tokens = token_get_all($source);
        $capturing = false;
        $braceDepth = 0;
        $functionTokens = '';

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            $tokenText = is_array($token) ? $token[1] : $token;

            if (!$capturing) {
                if (is_array($token) && $token[0] === T_FUNCTION) {
                    // Look ahead for function name
                    $nextToken = $tokens[$i + 2] ?? null;
                    if (is_array($nextToken) && $nextToken[0] === T_STRING && $nextToken[1] === $functionName) {
                        $capturing = true;
                        $functionTokens .= $tokenText;
                    }
                }
                continue;
            }

            $functionTokens .= $tokenText;

            if ($tokenText === '{') {
                $braceDepth++;
            } elseif ($tokenText === '}') {
                $braceDepth--;

                if ($braceDepth === 0) {
                    break;
                }
            }
        }

        return $functionTokens;
    }
}
