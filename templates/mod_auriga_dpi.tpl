
<div class="<?php echo $this->class; ?> ptw_broadcast"<?php echo $this->cssID; ?><?php if ($this->style): ?> style="<?php echo $this->style; ?>"<?php endif; ?>>
<?php if ($this->headline): ?>

<<?php echo $this->hl; ?>><?php echo $this->headline; ?></<?php echo $this->hl; ?>>
<?php endif; ?>

<form method="POST" action="<?php echo $this->formaction; ?>">
<div>
<?php echo $this->lngSearch; ?> <input type="text" name="search" <?php if (strlen($this->savedSearch)) echo 'value="' . $this->savedSearch . '" ' ?>/> <?php echo $this->lngIn; ?> 
<select name="field"><?php foreach ($this->options as $option): ?><option value="<?php echo $option; ?>" <?php if (strcmp($option, $this->savedField) == 0) echo ' selected="selected"' ?>><?php echo $this->$option; ?></option><?php endforeach; ?></select>
<input type="submit" class="submit" name="submit" value="<?php echo $this->lngSearchButton; ?>" />
</div>
<div>
	<?php echo $this->resultsperpage; ?> <select name="results"><?php foreach ($this->results as $result): ?><option value="<?php echo $result; ?>" <?php if ($result == $this->savedResults) echo ' selected="selected"'; ?>><?php echo $result; ?></option><?php endforeach; ?></select>
</div>
</form>

<?php if ($this->foundResults): ?>
<div class="resultinfo"><?php echo $this->searchResult; ?> <?php echo $this->resFrom; ?> - <?php echo $this->resTo; ?> <?php echo $this->of; ?> <?php echo $this->resTotal; ?></div>
<table>
	<thead>
	<tr>
		<th><?php echo $this->adventure; ?></th>
		<th colspan="2"><?php echo $this->title; ?></th>
		<th><?php echo $this->artist; ?></th>
		<th colspan="2"><?php echo $this->album; ?></th>
		<th><?php echo $this->year; ?></th>
		<th><?php echo $this->labelcode; ?></th>
		<th><?php echo $this->genres; ?></th>
	</tr>
	</thead>
	<tbody>
<?php foreach ($this->foundsongs as $song): ?>
	<tr>
		<td><a href="<?php echo $song['advurl']; ?>"><?php echo $song['adventure']; ?></a></td>
		<td><?php echo $song['special']; ?></td>
		<td><a href="<?php echo $song['titleurl']; ?>"><?php echo $song['title']; ?></a></td>
		<td><a href="<?php echo $song['artisturl']; ?>"><?php echo $song['artist']; ?></a></td>
		<td class="cover"><a href="<?php echo $song['idurl']; ?>"><?php echo $song['cover']; ?></a></td>
		<td><a href="<?php echo $song['albumurl']; ?>"><?php echo $song['album']; ?></a></td>
		<td><?php echo $song['year']; ?></td>
		<td><a href="<?php echo $song['labelurl']; ?>"><?php echo $song['labelcode']; ?></a></td>
		<td><?php echo join(array_values($song['genres']), '/') ; ?></td>
	</tr>
<?php endforeach; ?>
	</tbody>
</table>
<?php echo $this->pagination; ?>
<?php endif; ?>
</div>
