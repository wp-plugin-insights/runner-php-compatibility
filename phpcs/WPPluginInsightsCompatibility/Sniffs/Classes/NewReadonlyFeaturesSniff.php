<?php

declare(strict_types=1);

namespace WpPluginInsightsCompatibility\Sniffs\Classes;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class NewReadonlyFeaturesSniff extends Sniff
{
    public function register(): array
    {
        $tokens = [];

        if (defined('T_READONLY')) {
            $tokens[] = T_READONLY;
        }

        return $tokens;
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $nextPointer = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($nextPointer !== false && $tokens[$nextPointer]['code'] === T_CLASS) {
            if ($this->supportsBelow('8.1') === true) {
                $phpcsFile->addError(
                    'Readonly classes are not supported in PHP 8.1 or earlier',
                    $stackPtr,
                    'ReadonlyClassFound'
                );
            }

            return;
        }

        if ($this->supportsBelow('8.0') === true) {
            $phpcsFile->addError(
                'Readonly properties are not supported in PHP 8.0 or earlier',
                $stackPtr,
                'ReadonlyPropertyFound'
            );
        }
    }
}
