<h2>Gruppredigering</h2>


<div id="group-edit-wrapper" >
<form action="group/$gid" id="group-edit-form" method="post" >
<div id="group-edit-name-wrapper" >
<label id="group-edit-name-label" for="group-edit-name" >Gruppnamn: </label>
<input type="text" id="group-edit-name" name="groupname" value="$name" />
</div>
<div id="group-edit-name-end"></div>
<div id="group-edit-select-wrapper" >
<label id="group_members_select_label"  for="group_members_select" >Medlemmar:</label>
$selector

</div>
$drop
<div id="group_members_select_end"></div>
<div id="group-edit-submit-wrapper" >
<input type="submit" name="submit" value="Spara" >
</div>

<div id="group-edit-select-end" ></div>
</form>
</div>
