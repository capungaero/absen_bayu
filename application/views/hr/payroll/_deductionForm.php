<table class="table text-start">
	<?php foreach ($deduction['list'] as $row) { ?>
		<tr>
			<th style="width: 30%"><?= $row['name'] ?></th>
			<td>
				<input autocomplete="off" type="text" class="form-control rupiah" required="" name="deduction[<?= $row['deduction_id'] ?>]" value="<?= format_rp($row['amount']) ?>">
			</td>
			<td style="width: 40%">
				<input autocomplete="off" type="text" class="form-control" name="deduction_note[<?= $row['deduction_id'] ?>]" value="<?= htmlspecialchars(isset($row['note']) ? $row['note'] : '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Keterangan potongan">
			</td>
		</tr>
	<?php } ?>
</table>

<script type="text/javascript">
	function format_rp(angka){
		var rupiah = '';		
		var angkarev = angka.toString().split('').reverse().join('');
		for(var i = 0; i < angkarev.length; i++) if(i%3 == 0) rupiah += angkarev.substr(i,3)+'.';
		return 'Rp. '+rupiah.split('',rupiah.length-1).reverse().join('');
	}
	 
	function format_angka(rupiah){
		return parseInt(rupiah.replace(/,.*|[^0-9]/g, ''), 10)*1;
	}

	$(document).ready(function(){
		$(".rupiah").keyup(function(){
			value = this.value;
			angka = format_angka(value);
			if(isNaN(angka)){
				this.value = '';
			}else{
				rupiah = format_rp(angka);
				this.value = rupiah;
			}

			
		})
	})
</script>
