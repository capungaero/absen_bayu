<table class="table text-start">
	<?php foreach ($insentif['list'] as $row) { ?>
		<tr>
			<th><?= $row['name'] ?></th>
			<td>
				<?php if($row['formula']){ ?>
					<?= format_rp($row['amount']) ?>
					<input type="hidden" name="default[]" value="<?= format_rp($row['amount']) ?>">
				
				<?php }else{ ?>
					<input autocomplete="off" type="text" class="form-control rupiah" required="" name="insentif[<?= $row['insentif_id'] ?>]" value="<?= format_rp($row['amount']) ?>">
				<?php } ?>
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