<?php

namespace Drupal\Tests\search_api_autocomplete\Unit;

use Drupal\search_api_autocomplete\AutocompleteFormUtility;

/**
 * Tests various utility methods of the Search API Autocomplete module.
 *
 * @group search_api_autocomplete
 *
 * @coversDefaultClass \Drupal\search_api_autocomplete\AutocompleteFormUtility
 */
class AutocompleteFormUtilityTest extends \PHPUnit_Framework_TestCase {

  /**
   * Tests splitting of user input into complete and incomplete words.
   *
   * @covers ::splitKeys
   *
   * @dataProvider providerTestSplitKeys
   */
  public function testSplitKeys($keys, array $expected) {
    $this->assertEquals($expected, AutocompleteFormUtility::splitKeys($keys));
  }

  /**
   * Data provider for testSplitKeys().
   */
  public function providerTestSplitKeys() {
    $data = [];
    $data['simple-word'] = ['word', ['', 'word']];
    $data['simple-word-dash'] = ['word-dash', ['', 'word-dash']];
    $data['whitespace-right-side'] = ['word-dash ', ['word-dash', '']];
    $data['quote-word-start'] = ['"word" other', ['"word"', 'other']];
    $data['quote-word-end'] = ['word "other"', ['word "other"', '']];

    return $data;
  }

}
