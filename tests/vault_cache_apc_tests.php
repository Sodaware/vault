<?php

require_once dirname(__FILE__) . '/../vault.php';

class VaultCacheApcTests extends PHPUnit_Framework_TestCase
{

	/**
	 * Runs first so that machines without APC installed will skip the rest of
	 * the tests.
	 */
    public function testIsAvailable()
    {
    	try {
	    	$provider = new Vault_Cache_APC();
	    } catch (Exception $e) {
			$this->assertFalse(true, "APC module must be enabled");
		}

		$this->assertEquals(1, ini_get('apc.enable_cli'), "apc.enable_cli must be set for testing");
    }

	/**
	 * @depends testIsAvailable
	 */
    public function testFetchInvalidKey()
    {
    	$this->assertNull(Vault::fetch(''));
    }

	/**
	 * @depends testIsAvailable
	 */
    public function testFetchValidKey()
    {
    	Vault::store('valid_key', 'value');
    	$this->assertEquals('value', Vault::fetch('valid_key'));
    }

    public function setUp()
    {
    	Vault::setup('APC');
    	ini_set('apc.enable_cli', 1);
    }


}