<?php

namespace MPHB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StringEncryptHelper {

	private const ENCRYPTION_NAME       = 'aes-256-ctr';
	private const ENCRIPTION_SALT       = 'mphb_non_secret_salt';
	private const ENCRIPTION_PASSPHRASE = 'mphb_non_secret_passphrase';


	private function __construct() {}


	public static function encryptString( ?string $stringToEncrypt ): string {

        if ( empty( $stringToEncrypt ) ||
			! function_exists( 'openssl_encrypt' ) ||
			! in_array( self::ENCRYPTION_NAME, openssl_get_cipher_methods() )
		) {
            return '' . $stringToEncrypt;
        }

        $initVectorLength = openssl_cipher_iv_length( self::ENCRYPTION_NAME );
        $initVector       = openssl_random_pseudo_bytes( $initVectorLength );
		$passPhrase       = ( function_exists('wp_salt') && wp_salt() ) ? wp_salt() : self::ENCRIPTION_PASSPHRASE;

        $encryptedString = openssl_encrypt(
            $stringToEncrypt . self::ENCRIPTION_SALT,
            self::ENCRYPTION_NAME,
            $passPhrase,
            0,
            $initVector
        );

        return base64_encode( $initVector . $encryptedString );
    }

    public static function decryptString( ?string $stringToDecrypt ): string {

		if ( empty( $stringToDecrypt ) ||
			! function_exists( 'openssl_encrypt' ) ||
			! in_array( self::ENCRYPTION_NAME, openssl_get_cipher_methods() )
		) {
            return '' . $stringToDecrypt;
        }

        $encryptedString = base64_decode( $stringToDecrypt, true );

        $initVectorLength = openssl_cipher_iv_length( self::ENCRYPTION_NAME );
        $initVector       = substr( $encryptedString, 0, $initVectorLength );
        $encryptedString  = substr( $encryptedString, $initVectorLength );
		$passPhrase       = ( function_exists('wp_salt') && wp_salt() ) ? wp_salt() : self::ENCRIPTION_PASSPHRASE;

        $decryptedString = openssl_decrypt(
            $encryptedString,
            self::ENCRYPTION_NAME,
            $passPhrase,
            0,
            $initVector
        );

        if ( ! $decryptedString ||
			self::ENCRIPTION_SALT !== substr( $decryptedString, - strlen( self::ENCRIPTION_SALT ) )
		) {
            return $stringToDecrypt;
        }

        return substr( $decryptedString, 0, - strlen( self::ENCRIPTION_SALT ) );
    }
}
