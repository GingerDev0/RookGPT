<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('activity', 'Team activity', 'Audit trail of member, conversation, API key, and team changes.');

if (!$activeTeam): ?>
  <div class="notice">Create or join a team before viewing activity.</div>
<?php else: ?>
  <section class="panel" id="recent-changes">
    <div class="eyebrow">Recent changes</div>
    <?php if ($teamChanges === []): ?>
      <div class="notice" style="margin-top:12px;">No team changes have been recorded yet.</div>
    <?php else: ?>
      <div class="changes-wrap">
        <table class="changes-table">
          <thead><tr><th>When</th><th>Changed by</th><th>Change</th><th>Target</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach ($teamChanges as $change): ?>
              <tr>
                <td><?= e(date('d M · H:i', strtotime((string) $change['created_at']))) ?></td>
                <td><?= e((string) ($change['actor_username'] ?: 'System')) ?></td>
                <td><span class="change-action"><?= e((string) $change['action']) ?></span></td>
                <td><span class="change-target"><?= e((string) ($change['target_label'] ?: ucfirst((string) $change['target_type']))) ?></span><div class="muted"><?= e((string) $change['target_type']) ?></div></td>
                <td><span class="change-details"><?= e((string) ($change['details'] ?: 'No extra details.')) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pagination-row">
        <div class="pagination-meta">Showing <?= e((string) ($teamChangesOffset + 1)) ?>–<?= e((string) min($teamChangesOffset + $changesPerPage, $teamChangesTotal)) ?> of <?= e((string) $teamChangesTotal) ?> changes</div>
        <?php if ($teamChangesTotalPages > 1): ?>
          <div class="pagination-links" aria-label="Recent changes pagination">
            <a class="pagination-link <?= $changesPage <= 1 ? 'disabled' : '' ?>" href="<?= e($changesPageUrl($changesPage - 1)) ?>">Prev</a>
            <?php $pageStart = max(1, $changesPage - 2); $pageEnd = min($teamChangesTotalPages, $changesPage + 2); if ($pageEnd - $pageStart < 4) { $pageStart = max(1, min($pageStart, $pageEnd - 4)); $pageEnd = min($teamChangesTotalPages, max($pageEnd, $pageStart + 4)); } ?>
            <?php for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++): ?><a class="pagination-link <?= $pageNumber === $changesPage ? 'active' : '' ?>" href="<?= e($changesPageUrl($pageNumber)) ?>"><?= e((string) $pageNumber) ?></a><?php endfor; ?>
            <a class="pagination-link <?= $changesPage >= $teamChangesTotalPages ? 'disabled' : '' ?>" href="<?= e($changesPageUrl($changesPage + 1)) ?>">Next</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif;
render_team_footer();
