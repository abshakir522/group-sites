<?php

declare(strict_types=1);

namespace Drupal\conferencekit_branding\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\file\FileInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides group-aware branding for conference microsites.
 */
#[Block(
  id: 'conferencekit_group_branding_block',
  admin_label: new TranslatableMarkup('Microsite branding'),
  category: new TranslatableMarkup('Conference Kit')
)]
final class ConferenceKitGroupBrandingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const LOGO_FIELD = 'field_site_logo';

  /**
   * Creates a ConferenceKitGroupBrandingBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly DomainNegotiatorInterface $domainNegotiator,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
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
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'use_site_logo' => TRUE,
      'use_site_name' => TRUE,
      'label_display' => '0',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['block_branding'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toggle branding elements'),
      '#description' => $this->t('Choose which group branding elements to show in this block instance.'),
    ];
    $form['block_branding']['use_site_logo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group logo'),
      '#default_value' => $this->configuration['use_site_logo'],
    ];
    $form['block_branding']['use_site_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group site title'),
      '#description' => $this->t('Uses the first available configured title field, then falls back to the group label.'),
      '#default_value' => $this->configuration['use_site_name'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $block_branding = $form_state->getValue('block_branding');

    $this->configuration['use_site_logo'] = (bool) $block_branding['use_site_logo'];
    $this->configuration['use_site_name'] = (bool) $block_branding['use_site_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $group = $this->getGroupFromActiveDomain();
    if (!$group instanceof GroupInterface) {
      return [
        '#cache' => [
          'contexts' => ['url.site.group'],
        ],
      ];
    }

    $logo_data = $this->configuration['use_site_logo']
      ? $this->getImageDataFromField($group, self::LOGO_FIELD)
      : NULL;

    $build = [
      '#theme' => 'conferencekit_group_branding',
      '#site_logo' => $logo_data ? $this->fileUrlGenerator->generateString($logo_data['uri']) : NULL,
      '#site_logo_alt' => $logo_data ? ($logo_data['alt'] ?: $this->t('Home')) : NULL,
      '#site_name' => $this->configuration['use_site_name'] ? $this->getSiteName($group) : NULL,
      '#cache' => [
        'contexts' => ['url.site.group'],
        'tags' => Cache::mergeTags($group->getCacheTags(), $logo_data['cache_tags'] ?? []),
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.site.group']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = parent::getCacheTags();
    $group = $this->getGroupFromActiveDomain();
    if ($group instanceof GroupInterface) {
      $cache_tags = Cache::mergeTags($cache_tags, $group->getCacheTags());
    }
    return $cache_tags;
  }

  /**
   * Gets the group assigned to the current active domain.
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
   * Gets image data from a supported group logo field.
   */
  private function getImageDataFromField(EntityInterface $entity, string $field_name): ?array {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return NULL;
    }

    $field = $entity->get($field_name);
    $item = $field->first();
    $referenced_entity = $item?->entity ?? NULL;

    if ($referenced_entity instanceof FileInterface) {
      return [
        'uri' => $referenced_entity->getFileUri(),
        'alt' => (string) ($item->alt ?? ''),
        'title' => (string) ($item->title ?? ''),
        'cache_tags' => $referenced_entity->getCacheTags(),
      ];
    }

    if ($referenced_entity instanceof EntityInterface) {
      $image_data = $this->getImageDataFromMedia($referenced_entity);
      if ($image_data) {
        $image_data['cache_tags'] = Cache::mergeTags(
          $image_data['cache_tags'] ?? [],
          $referenced_entity->getCacheTags(),
        );
      }
      return $image_data;
    }

    $value = $item?->getValue() ?? [];
    if (!empty($value['uri'])) {
      return [
        'uri' => (string) $value['uri'],
        'alt' => (string) ($value['alt'] ?? ''),
        'title' => (string) ($value['title'] ?? ''),
      ];
    }

    return NULL;
  }

  /**
   * Gets image data from a media entity, favoring its image source field.
   */
  private function getImageDataFromMedia(EntityInterface $media): ?array {
    foreach (['field_media_image', 'image', 'thumbnail'] as $field_name) {
      $image_data = $this->getImageDataFromField($media, $field_name);
      if ($image_data) {
        return $image_data;
      }
    }

    return NULL;
  }

  /**
   * Gets the group site name.
   */
  private function getSiteName(GroupInterface $group): string {
    foreach (['field_site_name', 'field_site_title'] as $field_name) {
      if ($group->hasField($field_name) && !$group->get($field_name)->isEmpty()) {
        return (string) $group->get($field_name)->value;
      }
    }

    return $group->label();
  }

}
