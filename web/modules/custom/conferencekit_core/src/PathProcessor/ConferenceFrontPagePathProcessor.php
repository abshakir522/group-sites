<?php

namespace Drupal\conferencekit_core\PathProcessor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Routes a conference domain root to that group's configured front page path.
 */
final class ConferenceFrontPagePathProcessor implements InboundPathProcessorInterface {

  private const FRONT_PAGE_FIELD = 'field_front_page_path';

  public function __construct(
    private readonly DomainNegotiatorInterface $domainNegotiator,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    if ($path !== '/') {
      return $path;
    }

    $group = $this->getGroupFromActiveDomain();
    if (!$group instanceof GroupInterface || !$group->hasField(self::FRONT_PAGE_FIELD)) {
      return $path;
    }

    return $this->getFrontPagePath($group) ?? $path;
  }

  /**
   * Gets the group assigned to the current domain.
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
   * Extracts and normalizes the configured front page path.
   */
  private function getFrontPagePath(GroupInterface $group): ?string {
    $field = $group->get(self::FRONT_PAGE_FIELD);
    if ($field->isEmpty()) {
      return NULL;
    }

    $item = $field->first();
    $value = $item->getValue();
    $path = (string) ($value['uri'] ?? $value['value'] ?? '');
    $path = trim($path);

    if (str_starts_with($path, 'internal:')) {
      $path = substr($path, strlen('internal:'));
    }

    if ($path === '' || $path === '<front>' || $path === '/') {
      return NULL;
    }

    if (UrlHelper::isExternal($path)) {
      return NULL;
    }

    return str_starts_with($path, '/') ? $path : '/' . $path;
  }

}
