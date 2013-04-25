
<div class="<?php echo $this->class; ?> ptw_adventurelist"<?php echo $this->cssID; ?><?php if ($this->style): ?> style="<?php echo $this->style; ?>"<?php endif; ?>>
<?php if ($this->headline): ?>

<<?php echo $this->hl; ?>><?php echo $this->headline; ?></<?php echo $this->hl; ?>>
<?php endif; ?>

<table>
<?php foreach ($this->adventures as $adventure): ?>
  <tr>
		<td><?php echo $adventure['numbering']; ?>.</td>
		<td><a href="<?php echo $adventure['url']; ?>" title="<?php echo $adventure['title']; ?>"><?php echo $adventure['title']; ?></a></td>
	</tr>
<?php endforeach; ?>
</table>

</div>
