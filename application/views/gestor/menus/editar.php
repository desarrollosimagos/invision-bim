<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2><?php echo $this->lang->line('heading_title_menus_edit'); ?> </h2>
        <ol class="breadcrumb">
            <li>
                <a href="<?php echo base_url() ?>home"><?php echo $this->lang->line('heading_home_menus_edit'); ?></a>
            </li>
            
            <li>
                <a href="<?php echo base_url() ?>menus"><?php echo $this->lang->line('heading_subtitle_menus_edit'); ?></a>
            </li>
           
            <li class="active">
                <strong><?php echo $this->lang->line('heading_info_menus_edit'); ?></strong>
            </li>
        </ol>
    </div>
</div>

<!-- Campos ocultos que almacenan los nombres del menú y el submenú de la vista actual -->
<input type="hidden" id="ident" value="<?php echo $ident; ?>">
<input type="hidden" id="ident_sub" value="<?php echo $ident_sub; ?>">

<div class="wrapper wrapper-content animated fadeInRight">
	<div class="row">
        <div class="col-lg-12">
			<div class="ibox float-e-margins">
				<div class="ibox-title">
					<h5><?php echo $this->lang->line('heading_info_menus_edit'); ?><small></small></h5>
					
				</div>
				<div class="ibox-content">
					<form id="form_menus" method="post" accept-charset="utf-8" class="form-horizontal">
						<div class="form-group"><label class="col-sm-2 control-label" ><?php echo $this->lang->line('edit_name_menus'); ?> *</label>
							<div class="col-sm-10"><input type="text" class="form-control" name="name" id="name" maxlength="150" value="<?php echo $editar[0]->name ?>"></div>
						</div>
						<div class="form-group"><label class="col-sm-2 control-label" ><?php echo $this->lang->line('edit_route_menus'); ?></label>
							<div class="col-sm-10"><input type="text" class="form-control"  maxlength="100" name="route" id="route" value="<?php echo $editar[0]->route ?>"></div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label" ><?php echo $this->lang->line('edit_action_menus'); ?></label>
							<div class="col-sm-10">
								<select class="form-control m-b" name="action_id" id="action_id">
									<option value="0" selected="">Seleccione</option>
									<?php foreach ($acciones as $accion) { ?>
										<option value="<?php echo $accion->id ?>"><?php echo $accion->name ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-2 control-label" ><?php echo $this->lang->line('edit_icon_menus'); ?></label>
							<div class="col-sm-10">
								<select class="form-control m-b" name="logo" id="logo">
									<option value="0" selected="">Seleccione</option>
									<?php foreach ($iconos as $icono) { ?>
										<option value="<?php echo $icono->class; ?>"><a><i class="<?php echo $icono->class; ?>"></i></a><span><?php echo $icono->name; ?></span></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-2">
								
							</div>
							<div id="icono" class="col-sm-10">
								
							</div>
						</div>
						<div class="form-group">
							<div class="col-sm-4 col-sm-offset-2">
								<input id="id_action" type="hidden" value="<?php echo $editar[0]->action_id ?>"/>
								<input id="id_logo" type="hidden" value="<?php echo $editar[0]->logo ?>"/>
								<input class="form-control"  type='hidden' id="id" name="id" value="<?php echo $id ?>"/>
								<button class="btn btn-white" id="volver" type="button"><?php echo $this->lang->line('edit_back_menus'); ?></button>
								<button class="btn btn-primary" id="edit" type="submit"><?php echo $this->lang->line('edit_save_menus'); ?></button>
							</div>
						</div>
					</form>
				</div>
			</div>
        </div>
    </div>
</div>
<script>
	$(document).ready(function(){

    $('input').on({
        keypress: function () {
            $(this).parent('div').removeClass('has-error');
        }
    });

    $('#volver').click(function () {
        url = '<?php echo base_url() ?>menus/';
        window.location = url;
    });
    
    // Al seleccionar un icono se visualiza una imagen referencial debajo
    $('#logo').change(function (){
		//~ $('#icono').html('');
		$('#icono').html('<span><i class="'+$('#logo').val()+'"></i></span>');
	
	});
    
    $("#action_id").select2('val', $("#id_action").val());
    $("#logo").select2('val', $("#id_logo").val());

    $("#edit").click(function (e) {

        e.preventDefault();  // Para evitar que se envíe por defecto

        if ($('#name').val().trim() === "") {

          
			swal("Disculpe,", "para continuar debe ingresar nombre");
			$('#name').parent('div').addClass('has-error');
			
        } else if ($('#route').val().trim() == "" && $('#action_id').val().trim() != "0") {
			
			swal("Disculpe,", "para continuar debe ingresar la ruta");
			$('#route').parent('div').addClass('has-error');
			
		} else if ($('#route').val().trim() != "" && $('#action_id').val().trim() == "0") {
			
			swal("Disculpe,", "para continuar debe seleccionar la acción a asociar");
			$('#action_id').parent('div').addClass('has-error');
			
		} else if ($('#logo').val().trim() == "0") {
			
			swal("Disculpe,", "para continuar debe seleccionar el icono a asociar");
			$('#logo').parent('div').addClass('has-error');
			
		} else {

            $.post('<?php echo base_url(); ?>CMenus/update', $('#form_menus').serialize(), function (response) {

				if (response[0] == '1') {
                    swal("Disculpe,", "este nombre se encuentra registrado");
                }else{
					swal({ 
						title: "Actualizar",
						 text: "Guardado con exito",
						  type: "success" 
						},
					function(){
					  window.location.href = '<?php echo base_url(); ?>menus';
					});
				

				}

            });
        }

    });
});

</script>
