<?php

declare(strict_types=1);

namespace Drupal\conferencekit_core\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\group\Entity\GroupInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filters session nodes by day using current group conference dates.
 */
#[ViewsFilter('conferencekit_session_day')]
final class SessionDayFromGroupDates extends InOperator {

  /**
   * {@inheritdoc}
   */
  protected $valueFormType = 'select';

  /**
   * {@inheritdoc}
   */
  public $no_operator = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $this->valueTitle = $this->t('Session day');
    $this->valueOptions = [];

    $start = $this->getScheduleStartDate();
    $end = $this->getScheduleEndDate();

    if (!$start) {
      $this->valueOptions = [
        '1' => $this->t('Day 1'),
        '2' => $this->t('Day 2'),
        '3' => $this->t('Day 3'),
      ];
      return $this->valueOptions;
    }

    $day_count = $this->getDayCount($start, $end);
    for ($day = 1; $day <= $day_count; $day++) {
      $this->valueOptions[(string) $day] = $this->t('Day @day', [
        '@day' => $day,
      ]);
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $days = $this->getSelectedDays();
    if (!$days) {
      return;
    }

    $start = $this->getScheduleStartDate();
    if (!$start) {
      return;
    }

    $table_alias = $this->query->ensureTable('node__field_date', $this->relationship);
    $field = $table_alias . '.field_date_value';

    $expressions = [];
    foreach ($days as $day) {
      $day_start = $start->modify('+' . ($day - 1) . ' days');
      $day_end = $day_start->modify('+1 day');
      $start_timestamp = (int) $day_start->getTimestamp();
      $end_timestamp = (int) $day_end->getTimestamp();

      $expressions[] = "($field >= $start_timestamp AND $field < $end_timestamp)";
    }

    if (!$expressions) {
      return;
    }

    $expression = '(' . implode(' OR ', $expressions) . ')';
    if ($this->operator === 'not in') {
      $expression = 'NOT ' . $expression;
    }

    $this->query->addWhereExpression($this->options['group'], $expression);
    $this->query->options['distinct'] = TRUE;
  }

  /**
   * Gets selected exposed/configured day numbers.
   *
   * @return int[]
   *   Day numbers.
   */
  private function getSelectedDays(): array {
    if (empty($this->value) || $this->value === 'All') {
      return [];
    }

    $values = is_array($this->value) ? $this->value : [$this->value];
    $days = [];
    foreach ($values as $value) {
      if ($value === 'All' || $value === '' || $value === 0 || $value === '0') {
        continue;
      }
      $day = (int) $value;
      if ($day > 0) {
        $days[] = $day;
      }
    }

    return array_values(array_unique($days));
  }

  /**
   * Gets the active group date field as a local midnight date.
   */
  private function getGroupDate(string $field_name): ?\DateTimeImmutable {
    $group = $this->getActiveGroup();
    if (!$group instanceof GroupInterface
      || !$group->hasField($field_name)
      || $group->get($field_name)->isEmpty()
    ) {
      return NULL;
    }

    $value = (string) $group->get($field_name)->value;
    if ($value === '') {
      return NULL;
    }

    $timezone = new \DateTimeZone(date_default_timezone_get());
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    return $date instanceof \DateTimeImmutable ? $date : NULL;
  }

  /**
   * Gets the current domain group.
   */
  private function getActiveGroup(): ?GroupInterface {
    if (function_exists('conferencekit_core_get_group_from_active_domain')) {
      return conferencekit_core_get_group_from_active_domain();
    }
    return NULL;
  }

  /**
   * Gets the schedule start date.
   */
  private function getScheduleStartDate(): ?\DateTimeImmutable {
    return $this->getGroupDate('field_start_date')
      ?? $this->getSmartDateBoundary('MIN');
  }

  /**
   * Gets the schedule end date.
   */
  private function getScheduleEndDate(): ?\DateTimeImmutable {
    return $this->getGroupDate('field_end_date')
      ?? $this->getSmartDateBoundary('MAX');
  }

  /**
   * Gets the earliest or latest session Smart Date as a local date.
   */
  private function getSmartDateBoundary(string $aggregate): ?\DateTimeImmutable {
    $aggregate = strtoupper($aggregate);
    if (!in_array($aggregate, ['MIN', 'MAX'], TRUE)) {
      return NULL;
    }

    try {
      $query = \Drupal::database()
        ->select('node__field_date', 'date_field');
      $query->innerJoin('node_field_data', 'node_field_data', 'node_field_data.nid = date_field.entity_id');
      $query
        ->condition('node_field_data.status', 1)
        ->condition('node_field_data.type', 'session')
        ->addExpression($aggregate . '(date_field.field_date_value)', 'boundary');

      $timestamp = $query->execute()->fetchField();
    }
    catch (\Exception) {
      return NULL;
    }

    if (!$timestamp) {
      return NULL;
    }

    $timezone = new \DateTimeZone(date_default_timezone_get());
    return (new \DateTimeImmutable('@' . (int) $timestamp))
      ->setTimezone($timezone)
      ->setTime(0, 0);
  }

  /**
   * Gets inclusive day count between start and end dates.
   */
  private function getDayCount(\DateTimeImmutable $start, ?\DateTimeImmutable $end): int {
    if (!$end || $end < $start) {
      return 1;
    }

    return max(1, min(31, (int) $start->diff($end)->days + 1));
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
    $tags = parent::getCacheTags();
    if ($group = $this->getActiveGroup()) {
      $tags = Cache::mergeTags($tags, $group->getCacheTags());
    }
    return $tags;
  }

}
