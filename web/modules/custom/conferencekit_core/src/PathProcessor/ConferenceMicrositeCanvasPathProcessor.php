<?php

declare(strict_types=1);

namespace Drupal\conferencekit_core\PathProcessor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves Canvas page paths within the active conference microsite.
 */
final class ConferenceMicrositeCanvasPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  private const FIELD_NAME = 'field_ck_microsite_path';
  private const PLUGIN_ID = 'conferencekit_canvas_page';

  public function __construct(
    private readonly DomainNegotiatorInterface $domainNegotiator,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    $normalized_path = $this->normalizeMicrositePath($path);
    if ($normalized_path === NULL || $this->shouldSkipPath($normalized_path)) {
      return $path;
    }

    $group = $this->getGroupFromActiveDomain();
    if (!$group instanceof GroupInterface) {
      return $path;
    }

    $page_id = $this->loadPageIdByGroupPath((int) $group->id(), $normalized_path);
    if ($page_id === NULL) {
      return $path;
    }

    return '/page/' . $page_id;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (!preg_match('#^/page/(\d+)$#', $path, $matches)) {
      return $path;
    }

    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheContexts(['url.site.group']);
    }

    $group = $this->getGroupFromActiveDomain();
    if (!$group instanceof GroupInterface) {
      return $path;
    }

    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheableDependency($group);
    }

    $page_id = (int) $matches[1];
    if (!$this->pageBelongsToGroup((int) $group->id(), $page_id)) {
      return $path;
    }

    $page = $this->entityTypeManager->getStorage('canvas_page')->load($page_id);
    if (!$page || !$page->hasField(self::FIELD_NAME) || $page->get(self::FIELD_NAME)->isEmpty()) {
      return $path;
    }

    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheableDependency($page);
    }

    return $this->normalizeMicrositePath((string) $page->get(self::FIELD_NAME)->value) ?? $path;
  }

  /**
   * Loads a Canvas page ID by active group and microsite path.
   */
  private function loadPageIdByGroupPath(int $group_id, string $path): ?int {
    $page_storage = $this->entityTypeManager->getStorage('canvas_page');
    $page_ids = $page_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition(self::FIELD_NAME . '.value', $path)
      ->execute();

    if (!$page_ids) {
      return NULL;
    }

    $relationship_storage = $this->entityTypeManager->getStorage('group_relationship');
    $relationship_ids = $relationship_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin_id', self::PLUGIN_ID)
      ->condition('gid', $group_id)
      ->condition('entity_id', array_values($page_ids), 'IN')
      ->range(0, 1)
      ->execute();

    if (!$relationship_ids) {
      return NULL;
    }

    $relationship = $relationship_storage->load(reset($relationship_ids));
    return $relationship ? (int) $relationship->getEntityId() : NULL;
  }

  /**
   * Checks whether a Canvas page belongs to the active group.
   */
  private function pageBelongsToGroup(int $group_id, int $entity_id): bool {
    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin_id', self::PLUGIN_ID)
      ->condition('gid', $group_id)
      ->condition('entity_id', $entity_id)
      ->range(0, 1)
      ->execute();

    return (bool) $ids;
  }

  /**
   * Gets the group assigned to the active domain.
   */
  private function getGroupFromActiveDomain(): ?GroupInterface {
    $domain = $this->domainNegotiator->getActiveDomain();
    if (!$domain) {
      return NULL;
    }

    $group_uuid = $domain->getThirdPartySetting('group_context_domain', 'group_uuid');
    if (!$group_uuid) {
      return NULL;
    }

    $group = $this->entityRepository->loadEntityByUuid('group', $group_uuid);
    return $group instanceof GroupInterface ? $group : NULL;
  }

  /**
   * Normalizes a microsite path field value.
   */
  private function normalizeMicrositePath(string $path): ?string {
    $path = trim($path);
    if ($path === '') {
      return NULL;
    }

    if (str_starts_with($path, 'internal:')) {
      $path = substr($path, strlen('internal:'));
    }

    if ($path === '' || $path === '<front>' || $path === '/') {
      return NULL;
    }

    if (UrlHelper::isExternal($path)) {
      return NULL;
    }

    $path = '/' . ltrim($path, '/');
    return rtrim($path, '/') ?: NULL;
  }

  /**
   * Checks paths that should remain globally routed.
   */
  private function shouldSkipPath(string $path): bool {
    return str_starts_with($path, '/admin')
      || str_starts_with($path, '/canvas')
      || str_starts_with($path, '/group')
      || str_starts_with($path, '/page')
      || str_starts_with($path, '/user')
      || str_starts_with($path, '/system')
      || str_starts_with($path, '/sites');
  }

}
