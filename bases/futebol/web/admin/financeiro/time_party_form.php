<?php
$teamPlayer=($entry['party_module']??'')==='jogadores';
$teamKind=empty($entry['id'])?'':($teamPlayer?'player':'external');
?>
<div class="c-card" style="margin:16px 0">
    <h3 id="teamPartyHeading"><?= ($entry['type']??'')==='expense'?'Quem recebe?':'De quem vem o valor?' ?></h3>
    <div class="c-form-grid">
        <div class="c-form-group">
            <label for="teamPartyKind">Identificacao</label>
            <select id="teamPartyKind" name="team_party_kind" class="c-input" required>
                <option value="">Selecione</option>
                <option value="player" <?= $teamKind==='player'?'selected':'' ?>>Jogador cadastrado</option>
                <option value="external" <?= $teamKind==='external'?'selected':'' ?>>Pessoa ou empresa identificada</option>
            </select>
        </div>
        <div class="c-form-group" id="teamPlayerGroup">
            <label for="teamPlayer">Jogador</label>
            <select id="teamPlayer" name="team_player_id" class="c-input">
                <option value="">Selecione</option>
                <?php foreach(financeTeamPlayers($pdo) as$teamPerson): ?>
                <option value="<?= (int)$teamPerson['id'] ?>" <?= $teamPlayer&&(int)$entry['party_id']===(int)$teamPerson['id']?'selected':'' ?>><?= htmlspecialchars($teamPerson['name']) ?><?= $teamPerson['status']!=='active'?' (inativo)':'' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="c-form-group" id="teamExternalGroup">
            <label for="teamExternal">Nome da pessoa ou empresa</label>
            <input id="teamExternal" name="team_party_name" class="c-input" maxlength="150" value="<?= htmlspecialchars(!$teamPlayer?(string)($entry['party_name']??''):'') ?>">
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const kind=document.getElementById('teamPartyKind'), player=document.getElementById('teamPlayer'),external=document.getElementById('teamExternal');
 const sync=()=>{document.getElementById('teamPlayerGroup').hidden=kind.value!=='player';document.getElementById('teamExternalGroup').hidden=kind.value!=='external';player.required=kind.value==='player';player.disabled=kind.value!=='player';external.required=kind.value==='external';external.disabled=kind.value!=='external';};
 const heading=()=>{document.getElementById('teamPartyHeading').textContent=document.getElementById('financeType').value==='expense'?'Quem recebe?':'De quem vem o valor?';};
 const dates=()=>{const paid=document.querySelector('[name=status]').value==='paid';document.querySelector('[name=paid_at]').required=paid;document.querySelector('[name=due_date]').required=!paid;};
 kind.addEventListener('change',sync);document.getElementById('financeType')?.addEventListener('change',heading);document.getElementById('financeCategory')?.addEventListener('change',()=>queueMicrotask(heading));document.querySelector('[name=status]')?.addEventListener('change',dates);sync();if(document.getElementById('financeType'))heading();if(document.querySelector('[name=status]'))dates();
});
</script>
