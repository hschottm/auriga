<?php if (is_array($this->broadcasts)): ?>
<div class="<?php echo $this->class; ?> ptw_broadcastlist"<?php echo $this->cssID; ?><?php if ($this->style): ?> style="<?php echo $this->style; ?>"<?php endif; ?>>
<?php if ($this->headline): ?>

<<?php echo $this->hl; ?>><?php echo $this->headline; ?></<?php echo $this->hl; ?>>
<?php endif; ?>
<h2><?php echo $this->adventure['numbering'] . '. ' . $this->adventure['title']; ?></h2>
<p class="shortstory"><?php echo $this->adventure['description']; ?></p>
<table>
	<thead>
	<tr>
		<th colspan="2"><?php echo $this->lngBroadcast; ?></th>
		<th><?php echo $this->lngDate; ?></th>
		<th><?php echo $this->lngLength; ?></th>
		<th><?php echo $this->lngFrequency; ?></th>
		<th><?php echo $this->lngSamplerate; ?></th>
		<th><?php echo $this->lngProvider; ?></th>
		<th></th>
	</tr>
	</thead>
	<tbody>
<?php foreach ($this->broadcasts as $broadcast): ?>
	<tr>
		<td><?php echo $broadcast['chapter']; ?>.</td>
		<td><?php if ($broadcast['isStory'] || $broadcast['isSpecial']) echo '<span class="' . (($broadcast['isSpecial']) ? 'special ' : '') . (($broadcast['isStory']) ? 'story ' : '') . '">'; ?><?php echo '<a href="' . $broadcast['url'] . '">' . $broadcast['title'] . '</a>'; ?><?php if ($broadcast['isStory']) echo '</span>'; ?></td>
		<td><?php echo $broadcast['date']; ?></td>
		<td><?php echo $broadcast['length']; ?></td>
		<td><?php echo $broadcast['frequency']; ?></td>
		<td><?php echo $broadcast['samplerate']; ?></td>
		<td><?php echo join(array_keys($broadcast['providers']), ',') ; ?></td>
		<td><?php foreach ($broadcast['files'] as $file) { echo '<a href="' . $file . '"><img src="system/modules/auriga/html/images/song.gif" title="' . $file . '" /></a>'; } ?></td>
	</tr>
<?php endforeach; ?>
	</tbody>
</table>
<h3><?php echo $this->lngProviders; ?></h3>
<ul class="providers">
<?php foreach ($this->providers as $provider): ?>
	<li><?php echo $provider['initials']; ?> = <?php if (strlen($provider['homepage'])): ?><?php echo '<a href="' . $provider['homepage'] . '" target="_blank">'; ?><?php endif; ?><?php echo $provider['name']; ?><?php if (strlen($provider['homepage'])): ?><?php echo '</a>'; ?><?php endif; ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>