<?php

declare(strict_types=1);

namespace Drupal\conferencekit_branding\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to extract the current domain's group ID.
 */
#[ViewsArgumentDefault(
  id: 'conferencekit_group_id_from_domain',
  title: new TranslatableMarkup('Group ID from active domain'),
)]
final class GroupIdFromActiveDomain extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * Constructs a GroupIdFromActiveDomain object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly DomainNegotiatorInterface $domainNegotiator,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('domain.negotiator'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument(): ?string {
    $group = $this->getActiveDomainGroup();
    return $group instanceof GroupInterface ? (string) $group->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['url.site.group'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $group = $this->getActiveDomainGroup();
    return $group instanceof GroupInterface ? $group->getCacheTags() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * Gets the group assigned to the active domain.
   */
  private function getActiveDomainGroup(): ?GroupInterface {
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

}
