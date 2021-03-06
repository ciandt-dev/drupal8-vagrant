<?php

/**
 * @file
 * Contains \Drupal\system\Tests\System\AdminTest.
 */

namespace Drupal\system\Tests\System;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\simpletest\WebTestBase;

/**
 * Tests output on administrative pages and compact mode functionality.
 *
 * @group system
 */
class AdminTest extends WebTestBase {

  /**
   * User account with all available permissions
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $admin_user;

  /**
   * User account with limited access to administration pages.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $web_user;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('locale');

  function setUp() {
    // testAdminPages() requires Locale module.
    parent::setUp();

    // Create an administrator with all permissions, as well as a regular user
    // who can only access administration pages and perform some Locale module
    // administrative tasks, but not all of them.
    $this->admin_user = $this->drupalCreateUser(array_keys(\Drupal::moduleHandler()->invokeAll('permission')));
    $this->web_user = $this->drupalCreateUser(array(
      'access administration pages',
      'translate interface',
    ));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Tests output on administrative listing pages.
   */
  function testAdminPages() {
    // Go to Administration.
    $this->drupalGet('admin');

    // Verify that all visible, top-level administration links are listed on
    // the main administration page.
    foreach ($this->getTopLevelMenuLinks() as $item) {
      $this->assertLink($item->getTitle());
      $this->assertLinkByHref($item->getUrlObject()->toString());
      // The description should appear below the link.
      $this->assertText($item->getDescription());
    }

    // For each administrative listing page on which the Locale module appears,
    // verify that there are links to the module's primary configuration pages,
    // but no links to its individual sub-configuration pages. Also verify that
    // a user with access to only some Locale module administration pages only
    // sees links to the pages they have access to.
    $admin_list_pages = array(
      'admin/index',
      'admin/config',
      'admin/config/regional',
    );

    foreach ($admin_list_pages as $page) {
      // For the administrator, verify that there are links to Locale's primary
      // configuration pages, but no links to individual sub-configuration
      // pages.
      $this->drupalLogin($this->admin_user);
      $this->drupalGet($page);
      $this->assertLinkByHref('admin/config');
      $this->assertLinkByHref('admin/config/regional/settings');
      $this->assertLinkByHref('admin/config/regional/date-time');
      $this->assertLinkByHref('admin/config/regional/language');
      $this->assertNoLinkByHref('admin/config/regional/language/detection/session');
      $this->assertNoLinkByHref('admin/config/regional/language/detection/url');
      $this->assertLinkByHref('admin/config/regional/translate');
      // On admin/index only, the administrator should also see a "Configure
      // permissions" link for the Locale module.
      if ($page == 'admin/index') {
        $this->assertLinkByHref("admin/people/permissions#module-locale");
      }

      // For a less privileged user, verify that there are no links to Locale's
      // primary configuration pages, but a link to the translate page exists.
      $this->drupalLogin($this->web_user);
      $this->drupalGet($page);
      $this->assertLinkByHref('admin/config');
      $this->assertNoLinkByHref('admin/config/regional/settings');
      $this->assertNoLinkByHref('admin/config/regional/date-time');
      $this->assertNoLinkByHref('admin/config/regional/language');
      $this->assertNoLinkByHref('admin/config/regional/language/detection/session');
      $this->assertNoLinkByHref('admin/config/regional/language/detection/url');
      $this->assertLinkByHref('admin/config/regional/translate');
      // This user cannot configure permissions, so even on admin/index should
      // not see a "Configure permissions" link for the Locale module.
      if ($page == 'admin/index') {
        $this->assertNoLinkByHref("admin/people/permissions#module-locale");
      }
    }
  }

  /**
   * Returns all top level menu links.
   *
   * @return \Drupal\Core\Menu\MenuLinkInterface[]
   */
  protected function getTopLevelMenuLinks() {
    $menu_tree = \Drupal::menuTree();

    // The system.admin link is normally the parent of all top-level admin links.
    $parameters = new MenuTreeParameters();
    $parameters->setRoot('system.admin')->excludeRoot()->setTopLevelOnly()->excludeHiddenLinks();
    $tree = $menu_tree->load(NULL, $parameters);
    $manipulators = array(
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      array('callable' => 'menu.default_tree_manipulators:flatten'),
    );
    $tree = $menu_tree->transform($tree, $manipulators);

    // Transform the tree to a list of menu links.
    $menu_links = array();
    foreach ($tree as $element) {
      $menu_links[] = $element->link;
    }

    return $menu_links;
  }

  /**
   * Test compact mode.
   */
  function testCompactMode() {
    // The front page defaults to 'user', which redirects to 'user/{user}'. We
    // cannot use '<front>', since this does not match the redirected url.
    $frontpage_url = 'user/' . $this->admin_user->id();

    $this->drupalGet('admin/compact/on');
    $this->assertResponse(200, 'A valid page is returned after turning on compact mode.');
    $this->assertUrl($frontpage_url, array(), 'The user is redirected to the front page after turning on compact mode.');
    $this->assertTrue($this->cookies['Drupal.visitor.admin_compact_mode']['value'], 'Compact mode turns on.');
    $this->drupalGet('admin/compact/on');
    $this->assertTrue($this->cookies['Drupal.visitor.admin_compact_mode']['value'], 'Compact mode remains on after a repeat call.');
    $this->drupalGet('');
    $this->assertTrue($this->cookies['Drupal.visitor.admin_compact_mode']['value'], 'Compact mode persists on new requests.');

    $this->drupalGet('admin/compact/off');
    $this->assertResponse(200, 'A valid page is returned after turning off compact mode.');
    $this->assertUrl($frontpage_url, array(), 'The user is redirected to the front page after turning off compact mode.');
    $this->assertEqual($this->cookies['Drupal.visitor.admin_compact_mode']['value'], 'deleted', 'Compact mode turns off.');
    $this->drupalGet('admin/compact/off');
    $this->assertEqual($this->cookies['Drupal.visitor.admin_compact_mode']['value'], 'deleted', 'Compact mode remains off after a repeat call.');
    $this->drupalGet('');
    $this->assertTrue($this->cookies['Drupal.visitor.admin_compact_mode']['value'], 'Compact mode persists on new requests.');
  }
}
