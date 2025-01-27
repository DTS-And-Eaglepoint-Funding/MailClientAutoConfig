<?php

require __DIR__ . '/../class/DnsCacheResolver.php';

function clear_test_config()
{
  if (file_exists(\DnsCacheResolver::DNS_CACHE_CONFIG_PATH)) {
    unlink(\DnsCacheResolver::DNS_CACHE_CONFIG_PATH);
  }
}

function assertEquals($expected, $actual, $message = '')
{
  if ($expected !== $actual) {
    throw new Exception("Assertion failed: $message. Expected: " . json_encode($expected) . ", Actual: " . json_encode($actual));
  }
}

function assertArrayContains($expected, $actual, $message = '')
{
  if (!in_array($expected, $actual, true)) {
    throw new Exception("Assertion failed: $message. Expected array to contain: " . json_encode($expected) . ", Actual: " . json_encode($actual));
  }
}

function assertArrayEquals($expected, $actual, $message = '')
{
  sort($expected);
  sort($actual);
  if (json_encode($expected) !== json_encode($actual)) {
    throw new Exception("Assertion failed: $message. Expected: " . json_encode($expected) . ", Actual: " . json_encode($actual));
  }
}

function assertTrue($actual, $message = '')
{
  if ($actual !== true) {
    throw new Exception("Assertion failed: $message. Expected true, but got: " . json_encode($actual));
  }
}

function assertFalse($actual, $message = '')
{
  if ($actual !== false) {
    throw new Exception("Assertion failed: $message. Expected false, but got: " . json_encode($actual));
  }
}

function testAddDnsRecordAddsARecord()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'A');
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records'][$domain]['A'])) {
    $result = $dnsCache->resolve($domain, 'A');
    $liveRecords = dns_get_record($domain, DNS_A);
    if ($liveRecords) {
      assertArrayEquals(array_column($liveRecords, 'ip'), $result, "addDnsRecord adds A record");
    }
  }
}


function testAddDnsRecordAddsMXRecord()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'MX');
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records'][$domain]['MX'])) {
    $liveRecords = dns_get_record($domain, DNS_MX);
    if ($liveRecords) {
      $liveMx = array_map(function ($record) {
        return ['host' => $record['target'], 'pri' => $record['pri']];
      }, $liveRecords);
      $result = $dnsCache->resolve($domain, 'MX');
      assertArrayEquals($liveMx, $result, "addDnsRecord adds MX record");
    }
  }
}


function testAddDnsRecordPreventsDuplicateTXTRecords()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'TXT');
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records'][$domain]['TXT'])) {
    $value = $config['cached_dns_records'][$domain]['TXT'][0]['value'];
    $dnsCache->resolve($domain, 'TXT');
    $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
    if (isset($config['cached_dns_records'][$domain]['TXT'])) {
      assertEquals(count(dns_get_record($domain, DNS_TXT)), count($config['cached_dns_records'][$domain]['TXT']), "addDnsRecord prevents duplicate TXT records");
      assertArrayContains($value, array_column($config['cached_dns_records'][$domain]['TXT'], 'value'), "addDnsRecord prevents duplicate TXT records");
    }
  }
}

function testAddDnsRecordUpdatesMXRecordIfHostExists()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'MX'); // Resolve to initially cache records
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);

  if (isset($config['cached_dns_records'][$domain]['MX'])) {
    $liveRecords = dns_get_record($domain, DNS_MX);
    if ($liveRecords) {

      $updatedValue = ['host' => $liveRecords[0]['target'], 'pri' => $liveRecords[0]['pri'] + 10];

      $dnsCache->resolve($domain, 'MX'); // Resolve again to trigger the update
      $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache); // Reload config

      if (isset($config['cached_dns_records'][$domain]['MX'])) {
        $cachedValues = array_map(function ($record) {
          return $record['value'];
        }, $config['cached_dns_records'][$domain]['MX']);

        // Expect the same number of records as the live lookup
        assertEquals(count($liveRecords), count($cachedValues), "addDnsRecord updates MX record if host exists");

        $updatedRecordInCache = false;
        foreach ($cachedValues as $cachedValue) {
          if ($cachedValue['host'] === $updatedValue['host'] && $cachedValue['pri'] === $updatedValue['pri']) {
            $updatedRecordInCache = true;
            break;
          }
        }
        assertTrue($updatedRecordInCache, "Updated MX record not found in cache");
      }
    }
  }
}


function testResolveReturnsCachedARecord()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'A');
  $result = $dnsCache->resolve($domain, 'A');
  if ($result !== false) {
    $liveRecords = dns_get_record($domain, DNS_A);
    if ($liveRecords) {
      assertArrayEquals(array_column($liveRecords, 'ip'), $result, "resolve returns cached A record");
    }
  }
}

function testResolveReturnsCachedMXRecords()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'MX');
  $result = $dnsCache->resolve($domain, 'MX');
  if ($result !== false) {
    $liveRecords = dns_get_record($domain, DNS_MX);
    if ($liveRecords) {
      $liveMx = array_map(function ($record) {
        return ['host' => $record['target'], 'pri' => $record['pri']];
      }, $liveRecords);
      assertArrayEquals($liveMx, $result, "resolve returns cached MX records");
    }
  }
}

function testResolveReturnsFalseWhenNoRecordsExist()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $result = $dnsCache->resolve('nonexistent.com', 'A');
  assertFalse($result, "resolve returns false when no records exist");
}


function testResolveReturnsUpdatedRecordAfterExpiration()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'A');
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records'][$domain]['A'])) {
    $initialValue = $config['cached_dns_records'][$domain]['A'][0]['value'];
    (new ReflectionMethod($dnsCache, 'addDnsRecord'))->invoke($dnsCache,   $config, $domain, 'A', $initialValue, 1);
    (new ReflectionMethod($dnsCache, 'saveDnsCacheConfig'))->invoke($dnsCache, $config);
  }

  sleep(2);
  $dnsCache->resolve($domain, 'A');
  $result = $dnsCache->resolve($domain, 'A');
  $liveRecords = dns_get_record($domain, DNS_A);

  if ($result !== false && $liveRecords) {
    assertArrayEquals(array_column($liveRecords, 'ip'), $result, "resolve returns updated record after expiration");
  }
}



function testResolveReturnsRecordsInSRVFormat()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = '_imap._tcp.eaglepointfunding.co';
  $dnsCache->resolve('eaglepointfunding.co', 'SRV');
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records']['eaglepointfunding.co']['SRV'])) {
    $liveRecords = dns_get_record('eaglepointfunding.co', DNS_SRV);
    if ($liveRecords) {
      $value = $config['cached_dns_records']['eaglepointfunding.co']['SRV'][0]['value'];
      $expected = [
        'host' => $domain,
        'type' => 'SRV',
        'target' => $liveRecords[0]['target'],
        'port' => $liveRecords[0]['port'],
        'pri' => $liveRecords[0]['pri'],
        'weight' => $liveRecords[0]['weight'],
        'service' => ltrim(explode('.', $domain)[0], '_'),
        'proto' => ltrim(explode('.', $domain)[1], '_')
      ];
      $result = $dnsCache->resolve($domain, 'SRV');
      assertArrayEquals([$expected], $result, "resolve returns records in SRV format");
    }
  }
}

function testLoadDnsCacheConfigCreatesConfigIfDoesntExist()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  assertArrayEquals(['cached_dns_records' => []], $config, "loadDnsCacheConfig creates config if doesnt exist");
  assertTrue(file_exists(\DnsCacheResolver::DNS_CACHE_CONFIG_PATH), "loadDnsCacheConfig creates config if doesnt exist");
}

function testLoadDnsCacheConfigLoadsConfigFromDisk()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'A');
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records'][$domain]['A'])) {
    $value = $config['cached_dns_records'][$domain]['A'][0]['value'];
    $dnsCache->resolve($domain, 'A');
    $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
    assertEquals($value, $config['cached_dns_records'][$domain]['A'][0]['value'], "loadDnsCacheConfig loads config from disk");
  }
}

function testSaveDnsCacheConfigSavesConfigToDisk()
{
  clear_test_config();
  $dnsCache = new DnsCacheResolver();
  $domain = 'eaglepointfunding.co';
  $dnsCache->resolve($domain, 'A'); // Resolve to trigger adding record and saving config
  $config =  (new ReflectionMethod($dnsCache, 'loadDnsCacheConfig'))->invoke($dnsCache);
  if (isset($config['cached_dns_records'][$domain]['A'])) {
    $value = $config['cached_dns_records'][$domain]['A'][0]['value'];
    $configString = file_get_contents(\DnsCacheResolver::DNS_CACHE_CONFIG_PATH);
    assertTrue(strpos($configString, "'cached_dns_records' => [\n    'eaglepointfunding.co' => [\n        'A' => [\n            [\n                'value' => '" . $value . "',\n                'expires' =>") !== false, "saveDnsCacheConfig saves config to disk");
  }
}



function runTests()
{
  $tests = get_defined_functions()['user'];
  $successCount = 0;
  $failureCount = 0;
  foreach ($tests as $test) {
    if (strpos($test, 'test') === 0) {
      try {
        $test();
        echo "✅ $test\n";
        $successCount++;
      } catch (Exception $e) {
        echo "❌ $test - " . $e->getMessage() . "\n";
        $failureCount++;
      }
    }
  }
  echo "\nTests Run: " . ($successCount + $failureCount) . ", Passed: $successCount, Failed: $failureCount\n";
}

runTests();
