<?php

declare(strict_types=1);

namespace WpPluginInsightsCompatibility\Sniffs\Classes;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class NewPropertyPromotionSniff extends Sniff
{
    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->supportsBelow('7.4') === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($this->inClassScope($phpcsFile, $stackPtr, false) === false) {
            return;
        }

        $namePointer = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($namePointer === false || strtolower($tokens[$namePointer]['content']) !== '__construct') {
            return;
        }

        $openParenthesis = $tokens[$stackPtr]['parenthesis_opener'] ?? null;
        $closeParenthesis = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if (!is_int($openParenthesis) || !is_int($closeParenthesis)) {
            return;
        }

        $visibilityTokens = [
            T_PUBLIC,
            T_PROTECTED,
            T_PRIVATE,
        ];

        $position = $openParenthesis + 1;

        while (($position = $phpcsFile->findNext($visibilityTokens, $position, $closeParenthesis)) !== false) {
            $phpcsFile->addError(
                'Constructor property promotion is not supported in PHP 7.4 or earlier',
                $position,
                'Found'
            );

            return;
        }
    }
}
