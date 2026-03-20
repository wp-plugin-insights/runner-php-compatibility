<?php

declare(strict_types=1);

namespace WpPluginInsightsCompatibility\Sniffs\Classes;

use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class NewAsymmetricVisibilitySniff extends Sniff
{
    public function register(): array
    {
        $tokens = [];

        foreach (['T_PUBLIC_SET', 'T_PROTECTED_SET', 'T_PRIVATE_SET'] as $tokenName) {
            if (defined($tokenName)) {
                $tokens[] = constant($tokenName);
            }
        }

        return $tokens;
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $propertyPointer = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($propertyPointer === false) {
            return;
        }

        $isStatic = false;
        $searchPointer = $stackPtr + 1;

        while (($modifierPointer = $phpcsFile->findNext(Tokens::$emptyTokens, $searchPointer, null, true)) !== false) {
            $modifierCode = $tokens[$modifierPointer]['code'];

            if ($modifierCode === T_STATIC) {
                $isStatic = true;
                break;
            }

            if ($modifierCode === T_VARIABLE) {
                break;
            }

            $searchPointer = $modifierPointer + 1;
        }

        if ($isStatic === true) {
            if ($this->supportsBelow('8.4') === true) {
                $phpcsFile->addError(
                    'Asymmetric visibility on static properties is not supported in PHP 8.4 or earlier',
                    $stackPtr,
                    'StaticFound'
                );
            }

            return;
        }

        if ($this->supportsBelow('8.3') === true) {
            $phpcsFile->addError(
                'Asymmetric property visibility is not supported in PHP 8.3 or earlier',
                $stackPtr,
                'Found'
            );
        }
    }
}
