<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CResumen extends CI_Controller {
	
	private $coin_rate;  // Para almacenar la tasa de cambio del dólar a bolívares
	
	// Mensaje de resultado de api de dolartoday
	private $coin_rate_message = array(
		'type' => '',
		'message' => ''
	);

	public function __construct() {
        parent::__construct();
       
		// Load database
        $this->load->model('MResumen');
        $this->load->model('MFondoPersonal');
        $this->load->model('MCuentas');
        $this->load->model('MProjects');
        $this->load->model('MCoinRate');
        
        // Load coin rate
        $this->load_rate();  // Load coin rate from api
        $this->coin_rate = $this->show_rate();  // Load coin rate from database
		
    }
	
	public function index()
	{
		$this->load->view('base');
		$data['ident'] = "Resumen";
		$data['ident_sub'] = "";
		$data['listar'] = $this->MResumen->obtener();
		
		// Mensaje de la api de dolartoday
		$data['coin_rate_message'] = $this->coin_rate_message;
		
		// Obtenemos el valor en dólares de las distintas divisas
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
		if($ct){
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		} else {
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=1d8edbe4f5d54857b1686c15befc4a85");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		}
		
		// Colectando los symbolos de todas las cryptomonedas soportadas por la plataforma de coinmarketcap
		$get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		$exchangeRates2 = json_decode($get2, true);
		$valor1anycoin = 0;
		$i = 0;
		$rate = $this->session->userdata('logged_in')['coin_iso'];
		$rates = array();
		foreach($exchangeRates2 as $divisa){
			if ($divisa['symbol'] == $rate){
				$i+=1;
				
				// Obtenemos el valor de la cryptomoneda del usuario en dólares
				$valor1anycoin = $divisa['price_usd'];
			}
			$rates[] = $divisa['symbol'];  // Colectamos los símbolos de todas las cryptomonedas
		}
		
		// Valor de 1 dólar en bolívares
		//~ $get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		//~ $exchangeRates3 = json_decode($get3, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1vef = $exchangeRates3['USD']['transferencia'];
		$valor1vef = $this->coin_rate;
		
		if (in_array($this->session->userdata('logged_in')['coin_iso'], $rates)) {
		
			$currency_user = 1/(float)$valor1anycoin;  // Tipo de moneda del usuario logueado
			
		} else if($this->session->userdata('logged_in')['coin_iso'] == 'VEF') {
		
			$currency_user = $valor1vef;  // Tipo de moneda del usuario logueado
		
		} else {
			
			$currency_user = $exchangeRates['rates'][$this->session->userdata('logged_in')['coin_iso']];  // Tipo de moneda del usuario logueado
			
		}
		
		// Armamos la lista de las cuentas con el monto disponible de cada una
		$listar = array();
		
		$cuentas = $this->MCuentas->obtener();
		
		$total_cuentas = 0;  // Para almacenar el total de la suma de los montos disponibles en todas las cuentas
		
		foreach($cuentas as $cuenta){
						
			// Proceso de búsqueda de grupos de inversores asociados a la cuenta
			$groups = $this->MCuentas->buscar_grupos($cuenta->id);
			$groups_names = "";
			foreach($groups as $group){
				$groups_names .= $group->name.",";
			}
			$groups_names = substr($groups_names, 0, -1);
			
			// Proceso de búsqueda de transacciones asociadas a la cuenta para calcular los montos totales y parciales
			// Suma general de las tablas 'transactions'
			$sum_transacctions = $this->MCuentas->sumar_transacciones($cuenta->id, 'transactions t');
			// Suma condicionada de las tablas 'transactions' y 'project_transactions'
			$find_transactions = $this->MCuentas->buscar_transacciones($cuenta->id, 'transactions t');
			$capital_disponible_total = 0;
			$capital_disponible_parcial = 0;
			$capital_disponible_moneda_usuario = 0;
			if(count($find_transactions) > 0){
				foreach($find_transactions as $t1){
					if($t1->status == 'approved'){ $capital_disponible_total += $t1->amount; $total_cuentas += $t1->amount;}
					$relations = $this->MCuentas->buscar_transaction_relation($t1->id);
					if(count($relations) == 0){
						if($t1->type == "withdraw" || $t1->type == "deposit"){
							$capital_disponible_parcial += $t1->amount;
						}
					}
				}
			}
			
			// Conversión del monto de cada cuenta a dólares
			$currency_account = $cuenta->coin_avr;  // Tipo de moneda de la cuenta
			
			// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
			if (in_array($currency_account, $rates)) {
				
				// Primero convertimos el valor de la cryptodivisa
				$valor1anycoin = 0;
				$i = 0;
				$rate = $currency_account;
				foreach($exchangeRates2 as $divisa){
					if ($divisa['symbol'] == $rate){
						$i+=1;
						
						// Obtenemos el valor de la cryptomoneda de la transacción en dólares
						$valor1anycoin = $divisa['price_usd'];
					}
				}
				
				$trans_usd = (float)$capital_disponible_total*(float)$valor1anycoin;
				
			}else if($currency_account == 'VEF'){
				
				$trans_usd = (float)$capital_disponible_total/(float)$valor1vef;
				
			}else{
				
				$trans_usd = (float)$capital_disponible_total/$exchangeRates['rates'][$currency_account];
				
			}
			
			// Conversión del monto de cada cuenta a la divisa del usuario logueado
			$capital_disponible_moneda_usuario = $trans_usd * $currency_user; 
			$capital_disponible_moneda_usuario = round($capital_disponible_moneda_usuario, $this->session->userdata('logged_in')['coin_decimals']);
			$capital_disponible_moneda_usuario = $capital_disponible_moneda_usuario." ".$this->session->userdata('logged_in')['coin_symbol'];
			
			$data_cuenta = array(
				'id' => $cuenta->id,
				'owner' => $cuenta->owner,
				'alias' => $cuenta->alias,
				'number' => $cuenta->number,
				'usuario' => $cuenta->usuario,
				'type' => $cuenta->type,
				'description' => $cuenta->description,
				'amount' => $cuenta->amount,
				'capital_disponible_total' => $capital_disponible_total,
				'capital_disponible_parcial' => $capital_disponible_parcial,
				'capital_disponible_moneda_usuario' => $capital_disponible_moneda_usuario,
				'status' => $cuenta->status,
				'coin' => $cuenta->coin,
				'coin_avr' => $cuenta->coin_avr,
				'coin_symbol' => $cuenta->coin_symbol,
				'coin_decimals' => $cuenta->coin_decimals,
				'tipo_cuenta' => $cuenta->tipo_cuenta,
				'd_create' => $cuenta->d_create,
				'groups_names' => $groups_names
			);
			
			$listar[] = $data_cuenta;
			
		}
		
		// Conversión del monto de cada cuenta a la divisa del usuario logueado
		$total_cuentas = $total_cuentas * $currency_user; 
		$total_cuentas = round($total_cuentas, $this->session->userdata('logged_in')['coin_decimals']);
		$total_cuentas = $total_cuentas." ".$this->session->userdata('logged_in')['coin_symbol'];
		
		// Conversión a objeto
		$listar = json_decode( json_encode( $listar ), false );
		
		$data['cuentas'] = $listar;
		$data['total_cuentas'] = $total_cuentas;
		$data['capital_pendiente'] = $this->MResumen->capitalPendiente();
		$data['fondo_plataforma'] = $this->fondos_json_platform();
		$data['fondo_usuarios'] = $this->fondos_json_users();
		$data['fondo_por_proyecto'] = $this->fondos_json_by_projects();
		$data['fondo_resumen'] = $this->fondos_json_resumen();
		$data['fondo_proyectos'] = $this->fondos_json_projects();
		
		// Filtro para cargar las vistas según el perfil del usuario logueado
		$perfil_id = $this->session->userdata('logged_in')['profile_id'];
		$perfil_folder = "";
		if($perfil_id == 1 || $perfil_id == 2){
			$perfil_folder = 'plataforma/';
		}else if($perfil_id == 3){
			$perfil_folder = 'inversor/';
		}else if($perfil_id == 4){
			$perfil_folder = 'asesor/';
		}else if($perfil_id == 5){
			$perfil_folder = 'gestor/';
		}
		$this->load->view($perfil_folder.'resumen/resumen', $data);
		
		$this->load->view('footer');
	}
	
	public function ajax_resumen()
    {
        $result = $this->MCuentas->obtener();
        echo json_encode($result);
    }
    
	public function fondos_json()
    {
        $result = $this->MResumen->fondos_json();
        echo json_encode($result);
    }	
    
	public function fondos_json_resumen()
    {
        // Obtenemos el valor en dólares de las distintas divisas
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
		if($ct){
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		} else {
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=1d8edbe4f5d54857b1686c15befc4a85");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		}
		
		// Colectando los symbolos de todas las cryptomonedas soportadas por la plataforma de coinmarketcap
		$get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		$exchangeRates2 = json_decode($get2, true);
		$valor1anycoin = 0;
		$i = 0;
		$rate = $this->session->userdata('logged_in')['coin_iso'];
		$rates = array();
		foreach($exchangeRates2 as $divisa){
			if ($divisa['symbol'] == $rate){
				$i+=1;
				
				// Obtenemos el valor de la cryptomoneda del usuario en dólares
				$valor1anycoin = $divisa['price_usd'];
			}
			$rates[] = $divisa['symbol'];  // Colectamos los símbolos de todas las cryptomonedas
		}
		
		// Valor de 1 dólar en bolívares
		//~ $get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		//~ $exchangeRates3 = json_decode($get3, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1vef = $exchangeRates3['USD']['transferencia'];
		$valor1vef = $this->coin_rate;
		
		if (in_array($this->session->userdata('logged_in')['coin_iso'], $rates)) {
		
			$currency_user = 1/(float)$valor1anycoin;  // Tipo de moneda del usuario logueado
			
		} else if($this->session->userdata('logged_in')['coin_iso'] == 'VEF') {
		
			$currency_user = $valor1vef;  // Tipo de moneda del usuario logueado
		
		} else {
			
			$currency_user = $exchangeRates['rates'][$this->session->userdata('logged_in')['coin_iso']];  // Tipo de moneda del usuario logueado
			
		}
        
        $fondos_details = $this->MResumen->fondos_json();  // Listado de fondos
		
		$resumen = array(
			'pending_entry' => 0,
			'pending_exit' => 0,
			'approved_capital' => 0,
			'capital_account_user' => 0,
			'capital_project_user' => 0,
			'capital_account_platform' => 0,
			'capital_project_platform' => 0,
			'capital_invested' => 0,
			'capital_in_projects' => 0,
			'returned_capital' => 0,
			'pending_invest' => 0,
			'retirement_capital_available' => 0,
			'capital_available_platform' => 0,
			'balance_sheet' => 0
		);
		
		$capital_account_user = 0;
		$capital_project_user = 0;
		$capital_account_platform = 0;
		$capital_project_platform = 0;
		
		// Si el usuario es de perfil administrador o plataforma
		if($this->session->userdata('logged_in')['profile_id'] == 1 || $this->session->userdata('logged_in')['profile_id'] == 2){
			
			// CÁLCULOS DEL RESUMEN GENERAL
			
			// Balance General			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				// Capital en cuenta de usuarios
				if($fondo->status == 'approved' && $fondo->project_id == 0 && $fondo->user_id > 0){
					$capital_account_user += $trans_usd;
				}
				// Capital en projectos de usuarios
				if($fondo->status == 'approved' && $fondo->project_id > 0 && $fondo->user_id > 0){
					$capital_project_user += $trans_usd;
				}
				// Capital en cuenta de plataforma
				if($fondo->status == 'approved' && $fondo->project_id == 0 && $fondo->user_id == 0){
					$capital_account_platform += $trans_usd;
				}
				// Capital en projectos de plataforma
				if($fondo->status == 'approved' && $fondo->project_id > 0 && $fondo->user_id == 0){
					$capital_project_platform += $trans_usd;
				}
				
			}  // Cierre del for each de transacciones para el balance general
			
			// Capital en Cuenta
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved'){
					
					$resumen['approved_capital'] += $trans_usd;  // Capital aprobado
					
					if($fondo->project_id == 0){
						// Suma de depósitos
						if($fondo->type == 'deposit'){
							$deposit_approved += $trans_usd;
						}
						// Suma de gastos
						if($fondo->type == 'expense'){
							$expense_approved += $trans_usd;
						}
						// Suma de ganancias
						if($fondo->type == 'profit'){
							$profit_approved += $trans_usd;
						}
						// Suma de retiros
						if($fondo->type == 'withdraw'){
							$withdraw_approved += $trans_usd;
						}
						// Suma de inversiones
						if($fondo->type == 'invest'){
							$invest_approved += $trans_usd;
						}
						// Suma de ventas
						if($fondo->type == 'sell'){
							$sell_approved += $trans_usd;
						}
					}
				}
				
			}  // Cierre del for each de transacciones para capital en cuenta
			
			$resumen['retirement_capital_available'] += $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			// Capital disponible por plataforma
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved' && $fondo->user_id == 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_approved += $trans_usd;
					}
					// Suma de gastos
					if($fondo->type == 'expense'){
						$expense_approved += $trans_usd;
					}
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_approved += $trans_usd;
					}
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para capital disponible por plataforma
			
			$resumen['capital_available_platform'] += $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			// Capital en Proyecto
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved' && $fondo->user_id == 0 && $fondo->project_id > 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_approved += $trans_usd;
					}
					// Suma de gastos
					if($fondo->type == 'expense'){
						$expense_approved += $trans_usd;
					}
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_approved += $trans_usd;
					}
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para capital en cuenta
			
			$resumen['capital_in_projects'] += $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			// Capital Invertido
			$deposit_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				if($fondo->status == 'approved' && $fondo->project_id > 0){
					
					$data_project = $this->MProjects->obtenerProyecto($fondo->project_id);  // Datos del proyecto
					
					// Si la moneda de la transacción difiere de la del proyecto
					if(count($data_project) > 0 && $currency != $data_project[0]->coin_avr){
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd *= (float)$valor1anycoin;
							}else{
								$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							}
							
						}else if($currency == 'VEF'){
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd /= (float)$valor1vef;
							}else{
								$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							}
							
						}else{
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd /= (float)$exchangeRates['rates'][$currency];
							}else{
								$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							}
							
						}
						
					}else{
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
					}
					
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$deposit_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para capital invertido
			
			$resumen['capital_invested'] += $deposit_approved + $sell_approved;
			
			// Dividendo
			$profit_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved' && $fondo->project_id > 0){
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el dividendo
			
			$resumen['returned_capital'] += $profit_approved;
			
			// Depósito Pendiente
			$deposit_waiting = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'waiting' && $fondo->project_id == 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_waiting += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el depósito pendiente
			
			$resumen['pending_entry'] += $deposit_waiting;
			
			// Retiro Pendiente
			$withdraw_waiting = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'waiting' && $fondo->project_id == 0){
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_waiting += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el retiro pendiente
			
			$resumen['pending_exit'] += $withdraw_waiting;
			
			// Inversión Pendiente
			$invest_waiting = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'waiting' && $fondo->project_id > 0){
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_waiting += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el retiro pendiente
			
			$resumen['pending_invest'] += $invest_waiting;
			
			// CIERRE DE CÁLCULOS DEL RESUMEN GENERAL
			
			//-----------------------------------------------------------------------------------------------------------------------------
			
			// CÁLCULOS DEL RESUMEN POR PLATAFORMA
			
			$resumen_platform = array(
				'name' => 'PLATAFORMA',
				'alias' => 'PLATAFORMA',
				'username' => 'PLATAFORMA',
				'capital_invested' => 0,
				'returned_capital' => 0,
				'retirement_capital_available' => 0,
				'capital_in_project' => 0
			);  // Para el resultado final (Resumen de montos de plataforma)
			
			// Capital Invertido
			$deposit_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si no tiene usuario asociado en user_id lo tratamos como transacción de PLATAFORMA
				if($fondo->status == 'approved' && $fondo->user_id == 0 && $fondo->project_id > 0){
					
					$data_project = $this->MProjects->obtenerProyecto($fondo->project_id);  // Datos del proyecto
					
					// Si la moneda de la transacción difiere de la del proyecto
					if(count($data_project) > 0 && $currency != $data_project[0]->coin_avr){
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd *= (float)$valor1anycoin;
							}else{
								$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							}
							
						}else if($currency == 'VEF'){
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd /= (float)$valor1vef;
							}else{
								$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							}
							
						}else{
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd /= (float)$exchangeRates['rates'][$currency];
							}else{
								$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							}
							
						}
						
					}else{
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
					}
					
					// Suma de depósitos
					if($fondo->type == 'invest'){
						$deposit_approved += $trans_usd;
					}
					
				}
				
			}  // Cierre del for each de transacciones para capital invertido
			
			$resumen_platform['capital_invested'] += $deposit_approved;
			
			// Dividendo
			$profit_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				// Si no tiene usuario asociado en user_id lo tratamos como transacción de PLATAFORMA
				if($fondo->status == 'approved' && $fondo->user_id == 0 && $fondo->project_id > 0){
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el dividendo
			
			$resumen_platform['returned_capital'] += $profit_approved;
				
			// Capital en Cuenta
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				// Si no tiene usuario asociado en user_id lo tratamos como transacción de PLATAFORMA
				if($fondo->status == 'approved' && $fondo->user_id == 0 && $fondo->project_id == 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_approved += $trans_usd;
					}
					// Suma de gastos
					if($fondo->type == 'expense'){
						$expense_approved += $trans_usd;
					}
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_approved += $trans_usd;
					}
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones
			
			$resumen_platform['retirement_capital_available'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			// Capital en Proyecto
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				// Si tiene proyecto asociado en project_id lo sumamos
				if($fondo->status == 'approved' && $fondo->user_id == 0 && $fondo->project_id > 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_approved += $trans_usd;
					}
					// Suma de gastos
					if($fondo->type == 'expense'){
						$expense_approved += $trans_usd;
					}
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_approved += $trans_usd;
					}
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el capital en proyecto
			
			$resumen_platform['capital_in_project'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			$decimals = 2;
			if($this->session->userdata('logged_in')['coin_decimals'] != ""){
				$decimals = $this->session->userdata('logged_in')['coin_decimals'];
			}
			$symbol = $this->session->userdata('logged_in')['coin_symbol'];
			
			// Conversión de los montos a la divisa del usuario
			$resumen_platform['capital_invested'] *= $currency_user; 
			$resumen_platform['capital_invested'] = round($resumen_platform['capital_invested'], $decimals);
			$resumen_platform['capital_invested'] = $resumen_platform['capital_invested']." ".$symbol;
			
			$resumen_platform['returned_capital'] *= $currency_user; 
			$resumen_platform['returned_capital'] = round($resumen_platform['returned_capital'], $decimals);
			$resumen_platform['returned_capital'] = $resumen_platform['returned_capital']." ".$symbol;
			
			$resumen_platform['retirement_capital_available'] *= $currency_user; 
			$resumen_platform['retirement_capital_available'] = round($resumen_platform['retirement_capital_available'], $decimals);
			$resumen_platform['retirement_capital_available'] = $resumen_platform['retirement_capital_available']." ".$symbol;
			
			$resumen_platform['capital_in_project'] *= $currency_user; 
			$resumen_platform['capital_in_project'] = round($resumen_platform['capital_in_project'], $decimals);
			$resumen_platform['capital_in_project'] = $resumen_platform['capital_in_project']." ".$symbol;
			
			// CIERRE DE CÁLCULOS DEL RESUMEN POR PLATAFORMA
			
			//-----------------------------------------------------------------------------------------------------------------------------
			
			// CÁLCULOS DEL RESUMEN POR USUARIO
			
			$resumen_users = array();  // Para el resultado final (Listado de usuarios con sus respectivos resúmenes)
			
			$ids_users = array();  // Para almacenar los ids de los usuarios que han registrado fondos
			
			// Colectamos los ids de los usuarios de las transacciones generales
			foreach($fondos_details as $fondo){
				
				if(!in_array($fondo->user_id, $ids_users)){
					if($fondo->user_id > 0){
						$ids_users[] = $fondo->user_id;
					}
				}
				
			}
			
			// Armamos una lista de fondos por usuario y lo almacenamos en el arreglo '$resumen_users'
			foreach($ids_users as $id_user){
				
				$resumen_user = array(
					'name' => '',
					'alias' => '',
					'username' => '',
					'capital_invested' => 0,
					'returned_capital' => 0,
					'retirement_capital_available' => 0,
					'capital_in_project' => 0,
					'capital_available' => 0
				);
				
				// Capital Invertido
				$deposit_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->user_id == $id_user){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						$resumen_user['name'] = $fondo->name;
						$resumen_user['alias'] = $fondo->alias;
						$resumen_user['username'] = $fondo->username;
						
						// Si tiene proyecto asociado en project_id lo sumamos
						if($fondo->status == 'approved' && $fondo->user_id > 0 && $fondo->project_id > 0){
							
							$data_project = $this->MProjects->obtenerProyecto($fondo->project_id);  // Datos del proyecto
					
							// Si la moneda de la transacción difiere de la del proyecto
							if(count($data_project) > 0 && $currency != $data_project[0]->coin_avr){
								
								// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
								if (in_array($currency, $rates)) {
									
									// Primero convertimos el valor de la cryptodivisa
									$valor1anycoin = 0;
									$i = 0;
									$rate = $currency;
									foreach($exchangeRates2 as $divisa){
										if ($divisa['symbol'] == $rate){
											$i+=1;
											
											// Obtenemos el valor de la cryptomoneda de la transacción en dólares
											$valor1anycoin = $divisa['price_usd'];
										}
									}
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd *= (float)$valor1anycoin;
									}else{
										$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
									}
									
								}else if($currency == 'VEF'){
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd /= (float)$valor1vef;
									}else{
										$trans_usd = (float)$fondo->amount/(float)$valor1vef;
									}
									
								}else{
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd /= (float)$exchangeRates['rates'][$currency];
									}else{
										$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
									}
									
								}
								
							}else{
								
								// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
								if (in_array($currency, $rates)) {
									
									// Primero convertimos el valor de la cryptodivisa
									$valor1anycoin = 0;
									$i = 0;
									$rate = $currency;
									foreach($exchangeRates2 as $divisa){
										if ($divisa['symbol'] == $rate){
											$i+=1;
											
											// Obtenemos el valor de la cryptomoneda de la transacción en dólares
											$valor1anycoin = $divisa['price_usd'];
										}
									}
									
									$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
									
								}else if($currency == 'VEF'){
									
									$trans_usd = (float)$fondo->amount/(float)$valor1vef;
									
								}else{
									
									$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
									
								}
								
							}
					
							// Suma de depósitos
							if($fondo->type == 'invest'){
								$deposit_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para capital invertido
				
				$resumen_user['capital_invested'] += $deposit_approved;
				
				// Dividendo
				$profit_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->user_id == $id_user){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene proyecto asociado en project_id lo sumamos
						if($fondo->status == 'approved' && $fondo->user_id > 0 && $fondo->project_id > 0){
							// Suma de depósitos
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el dividendo
				
				$resumen_user['returned_capital'] += $profit_approved;
				
				// Capital en Cuenta
				$deposit_waiting = 0;
				$expense_waiting = 0;
				$profit_waiting = 0;
				$withdraw_waiting = 0;
				$invest_waiting = 0;
				$sell_waiting = 0;
				$deposit_approved = 0;
				$expense_approved = 0;
				$profit_approved = 0;
				$withdraw_approved = 0;
				$invest_approved = 0;
				$sell_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->user_id == $id_user){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene proyecto asociado en project_id lo sumamos
						if($fondo->status == 'approved' && $fondo->user_id > 0 && $fondo->project_id == 0){
							// Suma de depósitos
							if($fondo->type == 'deposit'){
								$deposit_approved += $trans_usd;
							}
							// Suma de gastos
							if($fondo->type == 'expense'){
								$expense_approved += $trans_usd;
							}
							// Suma de ganancias
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
							// Suma de retiros
							if($fondo->type == 'withdraw'){
								$withdraw_approved += $trans_usd;
							}
							// Suma de inversiones
							if($fondo->type == 'invest'){
								$invest_approved += $trans_usd;
							}
							// Suma de ventas
							if($fondo->type == 'sell'){
								$sell_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el capital en cuenta
				
				$resumen_user['retirement_capital_available'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
				
				// Capital en Proyecto
				$deposit_waiting = 0;
				$expense_waiting = 0;
				$profit_waiting = 0;
				$withdraw_waiting = 0;
				$invest_waiting = 0;
				$sell_waiting = 0;
				$deposit_approved = 0;
				$expense_approved = 0;
				$profit_approved = 0;
				$withdraw_approved = 0;
				$invest_approved = 0;
				$sell_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->user_id == $id_user){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene proyecto asociado en project_id lo sumamos
						if($fondo->status == 'approved' && $fondo->project_id > 0){
							// Suma de depósitos
							if($fondo->type == 'deposit'){
								$deposit_approved += $trans_usd;
							}
							// Suma de gastos
							if($fondo->type == 'expense'){
								$expense_approved += $trans_usd;
							}
							// Suma de ganancias
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
							// Suma de retiros
							if($fondo->type == 'withdraw'){
								$withdraw_approved += $trans_usd;
							}
							// Suma de inversiones
							if($fondo->type == 'invest'){
								$invest_approved += $trans_usd;
							}
							// Suma de ventas
							if($fondo->type == 'sell'){
								$sell_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el capital en proyecto
				
				$resumen_user['capital_in_project'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
				
				// Capital disponible
				$resumen_user['capital_available'] = $resumen_user['retirement_capital_available'] + $resumen_user['capital_in_project'];
				
				$decimals = 2;
				if($this->session->userdata('logged_in')['coin_decimals'] != ""){
					$decimals = $this->session->userdata('logged_in')['coin_decimals'];
				}
				$symbol = $this->session->userdata('logged_in')['coin_symbol'];
				
				// Conversión de los montos a la divisa del usuario
				$resumen_user['capital_invested'] *= $currency_user; 
				$resumen_user['capital_invested'] = round($resumen_user['capital_invested'], $decimals);
				$resumen_user['capital_invested'] = $resumen_user['capital_invested']." ".$symbol;
				
				$resumen_user['returned_capital'] *= $currency_user; 
				$resumen_user['returned_capital'] = round($resumen_user['returned_capital'], $decimals);
				$resumen_user['returned_capital'] = $resumen_user['returned_capital']." ".$symbol;
				
				$resumen_user['retirement_capital_available'] *= $currency_user; 
				$resumen_user['retirement_capital_available'] = round($resumen_user['retirement_capital_available'], $decimals);
				$resumen_user['retirement_capital_available'] = $resumen_user['retirement_capital_available']." ".$symbol;
				
				$resumen_user['capital_in_project'] *= $currency_user; 
				$resumen_user['capital_in_project'] = round($resumen_user['capital_in_project'], $decimals);
				$resumen_user['capital_in_project'] = $resumen_user['capital_in_project']." ".$symbol;
				
				$resumen_user['capital_available'] *= $currency_user; 
				$resumen_user['capital_available'] = round($resumen_user['capital_available'], $decimals);
				$resumen_user['capital_available'] = $resumen_user['capital_available']." ".$symbol;
				
				$resumen_users[] = $resumen_user;
				
			}
			
			// CIERRE DE CÁLCULOS DEL RESUMEN POR USUARIO
			
			//-----------------------------------------------------------------------------------------------------------------------------
			
			// CÁLCULOS DEL RESUMEN POR PROYECTO
			
			$resumen_projects = array();  // Para el resultado final (Listado de usuarios con sus respectivos resúmenes)
			
			$ids_projects = array();  // Para almacenar los ids de los usuarios que han registrado fondos
			
			// Colectamos los ids de los proyectos de las transacciones generales
			foreach($fondos_details as $fondo){
				
				if(!in_array($fondo->project_id, $ids_projects)){
					if($fondo->project_id > 0){
						$ids_projects[] = $fondo->project_id;
					}
				}
				
			}
			
			// Armamos una lista de fondos por proyecto y lo almacenamos en el arreglo '$resumen_projects'
			foreach($ids_projects as $id_project){
				
				$resumen_project = array(
					'name' => '',
					'type' => '',
					'capital_invested' => 0,
					'returned_capital' => 0,
					'retirement_capital_available' => 0,
					'retirement_capital_available_coins' => array()
				);
				
				// Consultamos los montos disponibles por moneda del proyecto y los recolectamos en $resumen_project['retirement_capital_available_coins']
				$available_coins = $this->MResumen->fondos_json_projects_coin($id_project);
				
				if(count($available_coins) > 0){
					foreach($available_coins as $a_c){
						$resumen_project['retirement_capital_available_coins'][] = array('coin' => $a_c->coin_avr, 'amount' => $a_c->amount);
					}
				}
				
				// Capital Invertido
				$deposit_approved = 0;
				$sell_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->project_id == $id_project){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						$resumen_project['name'] = $fondo->project_name;
						$resumen_project['type'] = $fondo->project_type;
						
						// Si tiene depósitos aprobados
						if($fondo->status == 'approved' && $fondo->project_id > 0){
							
							$data_project = $this->MProjects->obtenerProyecto($fondo->project_id);  // Datos del proyecto
					
							// Si la moneda de la transacción difiere de la del proyecto
							if(count($data_project) > 0 && $currency != $data_project[0]->coin_avr){
								
								// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
								if (in_array($currency, $rates)) {
									
									// Primero convertimos el valor de la cryptodivisa
									$valor1anycoin = 0;
									$i = 0;
									$rate = $currency;
									foreach($exchangeRates2 as $divisa){
										if ($divisa['symbol'] == $rate){
											$i+=1;
											
											// Obtenemos el valor de la cryptomoneda de la transacción en dólares
											$valor1anycoin = $divisa['price_usd'];
										}
									}
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd *= (float)$valor1anycoin;
									}else{
										$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
									}
									
								}else if($currency == 'VEF'){
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd /= (float)$valor1vef;
									}else{
										$trans_usd = (float)$fondo->amount/(float)$valor1vef;
									}
									
								}else{
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd /= (float)$exchangeRates['rates'][$currency];
									}else{
										$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
									}
									
								}
								
							}else{
								
								// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
								if (in_array($currency, $rates)) {
									
									// Primero convertimos el valor de la cryptodivisa
									$valor1anycoin = 0;
									$i = 0;
									$rate = $currency;
									foreach($exchangeRates2 as $divisa){
										if ($divisa['symbol'] == $rate){
											$i+=1;
											
											// Obtenemos el valor de la cryptomoneda de la transacción en dólares
											$valor1anycoin = $divisa['price_usd'];
										}
									}
									
									$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
									
								}else if($currency == 'VEF'){
									
									$trans_usd = (float)$fondo->amount/(float)$valor1vef;
									
								}else{
									
									$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
									
								}
								
							}
					
							// Suma de depósitos
							if($fondo->type == 'invest'){
								$deposit_approved += $trans_usd;
							}
							// Suma de ventas
							if($fondo->type == 'sell'){
								$sell_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para capital invertido
				
				$resumen_project['capital_invested'] += $deposit_approved + $sell_approved;
				
				// Dividendo
				$profit_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->project_id == $id_project){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene ganancias aprobadas
						if($fondo->status == 'approved'){
							// Suma de depósitos
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el dividendo
				
				$resumen_project['returned_capital'] += $profit_approved;
				
				// Capital en Proyecto
				$deposit_waiting = 0;
				$expense_waiting = 0;
				$profit_waiting = 0;
				$withdraw_waiting = 0;
				$invest_waiting = 0;
				$sell_waiting = 0;
				$deposit_approved = 0;
				$expense_approved = 0;
				$profit_approved = 0;
				$withdraw_approved = 0;
				$invest_approved = 0;
				$sell_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->project_id == $id_project){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene transacciones aprobadas la sumamos independientemente del tipo
						if($fondo->status == 'approved'){
							// Suma de depósitos
							if($fondo->type == 'deposit'){
								$deposit_approved += $trans_usd;
							}
							// Suma de gastos
							if($fondo->type == 'expense'){
								$expense_approved += $trans_usd;
							}
							// Suma de ganancias
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
							// Suma de retiros
							if($fondo->type == 'withdraw'){
								$withdraw_approved += $trans_usd;
							}
							// Suma de inversiones
							if($fondo->type == 'invest'){
								$invest_approved += $trans_usd;
							}
							// Suma de ventas
							if($fondo->type == 'sell'){
								$sell_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el capital en proyecto
				
				$resumen_project['retirement_capital_available'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
				
				$decimals = 2;
				if($this->session->userdata('logged_in')['coin_decimals'] != ""){
					$decimals = $this->session->userdata('logged_in')['coin_decimals'];
				}
				$symbol = $this->session->userdata('logged_in')['coin_symbol'];
				
				// Conversión de los montos a la divisa del usuario
				$resumen_project['capital_invested'] *= $currency_user; 
				$resumen_project['capital_invested'] = round($resumen_project['capital_invested'], $decimals);
				$resumen_project['capital_invested'] = $resumen_project['capital_invested']." ".$symbol;
				
				$resumen_project['returned_capital'] *= $currency_user; 
				$resumen_project['returned_capital'] = round($resumen_project['returned_capital'], $decimals);
				$resumen_project['returned_capital'] = $resumen_project['returned_capital']." ".$symbol;
				
				$resumen_project['retirement_capital_available'] *= $currency_user; 
				$resumen_project['retirement_capital_available'] = round($resumen_project['retirement_capital_available'], $decimals);
				$resumen_project['retirement_capital_available'] = $resumen_project['retirement_capital_available']." ".$symbol;
				
				$resumen_projects[] = $resumen_project;
				
			}
			
			// CIERRE DE CÁLCULOS DEL RESUMEN POR PROYECTO
		
		}else{
			
			// CÁLCULOS DEL RESUMEN GENERAL
			
			// Balance General
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				// Capital en cuenta de usuarios
				if($fondo->status == 'approved' && $fondo->project_id == 0 && $fondo->user_id > 0){
					$capital_account_user += $trans_usd;
				}
				// Capital en projectos de usuarios
				if($fondo->status == 'approved' && $fondo->project_id > 0 && $fondo->user_id > 0){
					$capital_project_user += $trans_usd;
				}
				// Capital en cuenta de plataforma
				if($fondo->status == 'approved' && $fondo->project_id == 0 && $fondo->user_id == 0){
					$capital_account_platform += $trans_usd;
				}
				// Capital en projectos de plataforma
				if($fondo->status == 'approved' && $fondo->project_id > 0 && $fondo->user_id == 0){
					$capital_project_platform += $trans_usd;
				}
				
			}  // Cierre del for each de transacciones para el balance general
			
			// Capital en Cuenta
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id == 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_approved += $trans_usd;
					}
					// Suma de gastos
					if($fondo->type == 'expense'){
						$expense_approved += $trans_usd;
					}
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_approved += $trans_usd;
					}
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para capital en cuenta
			
			$resumen['retirement_capital_available'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			// Capital en Proyecto
			$deposit_waiting = 0;
			$expense_waiting = 0;
			$profit_waiting = 0;
			$withdraw_waiting = 0;
			$invest_waiting = 0;
			$sell_waiting = 0;
			$deposit_approved = 0;
			$expense_approved = 0;
			$profit_approved = 0;
			$withdraw_approved = 0;
			$invest_approved = 0;
			$sell_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id > 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_approved += $trans_usd;
					}
					// Suma de gastos
					if($fondo->type == 'expense'){
						$expense_approved += $trans_usd;
					}
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_approved += $trans_usd;
					}
					// Suma de inversiones
					if($fondo->type == 'invest'){
						$invest_approved += $trans_usd;
					}
					// Suma de ventas
					if($fondo->type == 'sell'){
						$sell_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para capital en proyecto
			
			$resumen['capital_in_projects'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
			
			// Capital Invertido
			$deposit_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción				
				
				if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id > 0){
					
					$data_project = $this->MProjects->obtenerProyecto($fondo->project_id);  // Datos del proyecto
					
					// Si la moneda de la transacción difiere de la del proyecto
					if($currency != $data_project[0]->coin_avr){
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd *= (float)$valor1anycoin;
							}else{
								$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							}
							
						}else if($currency == 'VEF'){
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd /= (float)$valor1vef;
							}else{
								$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							}
							
						}else{
							
							// Si el campo de tasa 'rate' es mayor a cero
							if((float)$fondo->rate > 0){
								$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
								//~ $trans_usd /= (float)$exchangeRates['rates'][$currency];
							}else{
								$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							}
							
						}
						
					}else{
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
					}
					
					// Suma de depósitos
					if($fondo->type == 'invest'){
						$deposit_approved += $trans_usd;
					}
					
				}
				
			}  // Cierre del for each de transacciones para capital invertido
			
			$resumen['capital_invested'] += $deposit_approved;
			
			// Dividendo
			$profit_approved = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id > 0){
					// Suma de ganancias
					if($fondo->type == 'profit'){
						$profit_approved += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el dividendo
			
			$resumen['returned_capital'] += $profit_approved;
			
			// Depósito Pendiente
			$deposit_waiting = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'waiting' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id == 0){
					// Suma de depósitos
					if($fondo->type == 'deposit'){
						$deposit_waiting += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el depósito pendiente
			
			$resumen['pending_entry'] += $deposit_waiting;
			
			// Retiro Pendiente
			$withdraw_waiting = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'waiting' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id == 0){
					// Suma de retiros
					if($fondo->type == 'withdraw'){
						$withdraw_waiting += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el retiro pendiente
			
			$resumen['pending_exit'] += $withdraw_waiting;
			
			// Inversión Pendiente
			$invest_waiting = 0;
			
			foreach($fondos_details as $fondo){
					
				// Conversión de cada monto a dólares
				$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
				
				// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
				if (in_array($currency, $rates)) {
					
					// Primero convertimos el valor de la cryptodivisa
					$valor1anycoin = 0;
					$i = 0;
					$rate = $currency;
					foreach($exchangeRates2 as $divisa){
						if ($divisa['symbol'] == $rate){
							$i+=1;
							
							// Obtenemos el valor de la cryptomoneda de la transacción en dólares
							$valor1anycoin = $divisa['price_usd'];
						}
					}
					
					$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
					
				}else if($currency == 'VEF'){
					
					$trans_usd = (float)$fondo->amount/(float)$valor1vef;
					
				}else{
					
					$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
					
				}
				
				if($fondo->status == 'waiting' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id > 0){
					// Suma de retiros
					if($fondo->type == 'deposit'){
						$invest_waiting += $trans_usd;
					}
				}
				
			}  // Cierre del for each de transacciones para el retiro pendiente
			
			$resumen['pending_invest'] += $invest_waiting;
			
			// CIERRE DE CÁLCULOS DEL RESUMEN GENERAL
			
			//-----------------------------------------------------------------------------------------------------------------------------
			
			// CÁLCULOS DEL RESUMEN POR PROYECTO
			
			$resumen_projects = array();  // Para el resultado final (Listado de proyectos con sus respectivos resúmenes)
			
			$ids_projects = array();  // Para almacenar los ids de los proyectos que han registrado fondos
			
			// Colectamos los ids de los usuarios de las transacciones generales
			foreach($fondos_details as $fondo){
				
				if(!in_array($fondo->project_id, $ids_projects)){
					if($fondo->project_id > 0 && $fondo->user_id == $this->session->userdata('logged_in')['id']){
						$ids_projects[] = $fondo->project_id;
					}
				}
				
			}
			
			// Armamos una lista de fondos por proyecto y lo almacenamos en el arreglo '$resumen_proyects'
			foreach($ids_projects as $id_project){
				
				$resumen_project = array(
					'name' => '',
					'type' => '',
					'capital_invested' => 0,
					'returned_capital' => 0,
					'retirement_capital_available' => 0
				);
				
				// Capital Invertido
				$deposit_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->project_id == $id_project){
							
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						$resumen_project['name'] = $fondo->project_name;
						$resumen_project['type'] = $fondo->project_type;
						
						// Si tiene depósitos aprobados
						if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id'] && $fondo->project_id > 0){
							
							$data_project = $this->MProjects->obtenerProyecto($fondo->project_id);  // Datos del proyecto
					
							// Si la moneda de la transacción difiere de la del proyecto
							if($currency != $data_project[0]->coin_avr){
								
								// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
								if (in_array($currency, $rates)) {
									
									// Primero convertimos el valor de la cryptodivisa
									$valor1anycoin = 0;
									$i = 0;
									$rate = $currency;
									foreach($exchangeRates2 as $divisa){
										if ($divisa['symbol'] == $rate){
											$i+=1;
											
											// Obtenemos el valor de la cryptomoneda de la transacción en dólares
											$valor1anycoin = $divisa['price_usd'];
										}
									}
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd *= (float)$valor1anycoin;
									}else{
										$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
									}
									
								}else if($currency == 'VEF'){
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd /= (float)$valor1vef;
									}else{
										$trans_usd = (float)$fondo->amount/(float)$valor1vef;
									}
									
								}else{
									
									// Si el campo de tasa 'rate' es mayor a cero
									if((float)$fondo->rate > 0){
										$trans_usd = (float)$fondo->amount*(float)$fondo->rate;
										//~ $trans_usd /= (float)$exchangeRates['rates'][$currency];
									}else{
										$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
									}
									
								}
								
							}else{
								
								// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
								if (in_array($currency, $rates)) {
									
									// Primero convertimos el valor de la cryptodivisa
									$valor1anycoin = 0;
									$i = 0;
									$rate = $currency;
									foreach($exchangeRates2 as $divisa){
										if ($divisa['symbol'] == $rate){
											$i+=1;
											
											// Obtenemos el valor de la cryptomoneda de la transacción en dólares
											$valor1anycoin = $divisa['price_usd'];
										}
									}
									
									$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
									
								}else if($currency == 'VEF'){
									
									$trans_usd = (float)$fondo->amount/(float)$valor1vef;
									
								}else{
									
									$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
									
								}
								
							}
					
							// Suma de depósitos
							if($fondo->type == 'invest'){
								$deposit_approved += $trans_usd;
							}
						}
						
					}
					
				}  // Cierre del for each de transacciones para capital invertido
				
				$resumen_project['capital_invested'] += $deposit_approved;
				
				// Dividendo
				$profit_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->project_id == $id_project){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene ganancias aprobadas
						if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id']){
							// Suma de depósitos
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el dividendo
				
				$resumen_project['returned_capital'] += $profit_approved;
				
				// Capital en Cuenta
				$deposit_waiting = 0;
				$expense_waiting = 0;
				$profit_waiting = 0;
				$withdraw_waiting = 0;
				$invest_waiting = 0;
				$sell_waiting = 0;
				$deposit_approved = 0;
				$expense_approved = 0;
				$profit_approved = 0;
				$withdraw_approved = 0;
				$invest_approved = 0;
				$sell_approved = 0;
				
				foreach($fondos_details as $fondo){
					
					if($fondo->project_id == $id_project){
						
						// Conversión de cada monto a dólares
						$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
						
						// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
						if (in_array($currency, $rates)) {
							
							// Primero convertimos el valor de la cryptodivisa
							$valor1anycoin = 0;
							$i = 0;
							$rate = $currency;
							foreach($exchangeRates2 as $divisa){
								if ($divisa['symbol'] == $rate){
									$i+=1;
									
									// Obtenemos el valor de la cryptomoneda de la transacción en dólares
									$valor1anycoin = $divisa['price_usd'];
								}
							}
							
							$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
							
						}else if($currency == 'VEF'){
							
							$trans_usd = (float)$fondo->amount/(float)$valor1vef;
							
						}else{
							
							$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
							
						}
						
						// Si tiene transacciones aprobadas la sumamos independientemente del tipo
						if($fondo->status == 'approved' && $fondo->user_id == $this->session->userdata('logged_in')['id']){
							// Suma de depósitos
							if($fondo->type == 'deposit'){
								$deposit_approved += $trans_usd;
							}
							// Suma de gastos
							if($fondo->type == 'expense'){
								$expense_approved += $trans_usd;
							}
							// Suma de ganancias
							if($fondo->type == 'profit'){
								$profit_approved += $trans_usd;
							}
							// Suma de retiros
							if($fondo->type == 'withdraw'){
								$withdraw_approved += $trans_usd;
							}
							// Suma de inversiones
							if($fondo->type == 'invest'){
								$invest_approved += $trans_usd;
							}
							// Suma de ventas
							if($fondo->type == 'sell'){
								$sell_approved += $trans_usd;
							}
						}
					}
					
				}  // Cierre del for each de transacciones para el capital en cuenta
				
				$resumen_project['retirement_capital_available'] = $deposit_approved + $expense_approved + $profit_approved + $withdraw_approved + $invest_approved + $sell_approved;
				
				$decimals = 2;
				if($this->session->userdata('logged_in')['coin_decimals'] != ""){
					$decimals = $this->session->userdata('logged_in')['coin_decimals'];
				}
				$symbol = $this->session->userdata('logged_in')['coin_symbol'];
				
				// Conversión de los montos a la divisa del usuario
				$resumen_project['capital_invested'] *= $currency_user; 
				$resumen_project['capital_invested'] = round($resumen_project['capital_invested'], $decimals);
				$resumen_project['capital_invested'] = $resumen_project['capital_invested']." ".$symbol;
				
				$resumen_project['returned_capital'] *= $currency_user; 
				$resumen_project['returned_capital'] = round($resumen_project['returned_capital'], $decimals);
				$resumen_project['returned_capital'] = $resumen_project['returned_capital']." ".$symbol;
				
				$resumen_project['retirement_capital_available'] *= $currency_user; 
				$resumen_project['retirement_capital_available'] = round($resumen_project['retirement_capital_available'], $decimals);
				$resumen_project['retirement_capital_available'] = $resumen_project['retirement_capital_available']." ".$symbol;
				
				$resumen_projects[] = $resumen_project;
				
			}
			
			// CIERRE DE CÁLCULOS DEL RESUMEN POR PROYECTO
		
		}  // Cierre de validación de perfil
		
		$decimals = 2;
		if($this->session->userdata('logged_in')['coin_decimals'] != ""){
			$decimals = $this->session->userdata('logged_in')['coin_decimals'];
		}
		$symbol = $this->session->userdata('logged_in')['coin_symbol'];
		
		// Conversión de los montos a la divisa del usuario
		$resumen['pending_entry'] *= $currency_user; 
		$resumen['pending_entry'] = round($resumen['pending_entry'], $decimals);
		$resumen['pending_entry'] = $resumen['pending_entry']." ".$symbol;
		
		$resumen['pending_exit'] *= $currency_user; 
		$resumen['pending_exit'] = round($resumen['pending_exit'], $decimals);
		$resumen['pending_exit'] = $resumen['pending_exit']." ".$symbol;
		
		$resumen['approved_capital'] *= $currency_user; 
		$resumen['approved_capital'] = round($resumen['approved_capital'], $decimals);
		$resumen['approved_capital'] = $resumen['approved_capital']." ".$symbol;
		
		$resumen['capital_invested'] *= $currency_user; 
		$resumen['capital_invested'] = round($resumen['capital_invested'], $decimals);
		$resumen['capital_invested'] = $resumen['capital_invested']." ".$symbol;
		
		$resumen['returned_capital'] *= $currency_user; 
		$resumen['returned_capital'] = round($resumen['returned_capital'], $decimals);
		$resumen['returned_capital'] = $resumen['returned_capital']." ".$symbol;
		
		$resumen['pending_invest'] *= $currency_user; 
		$resumen['pending_invest'] = round($resumen['pending_invest'], $decimals);
		$resumen['pending_invest'] = $resumen['pending_invest']." ".$symbol;
		
		$resumen['retirement_capital_available'] *= $currency_user;
		$resumen['retirement_capital_available'] = round($resumen['retirement_capital_available'], $decimals);
		$resumen['retirement_capital_available'] = $resumen['retirement_capital_available']." ".$symbol;
		
		$resumen['capital_in_projects'] *= $currency_user;
		$resumen['capital_in_projects'] = round($resumen['capital_in_projects'], $decimals);
		$resumen['capital_in_projects'] = $resumen['capital_in_projects']." ".$symbol;
		
		$resumen['capital_available_platform'] *= $currency_user;
		$resumen['capital_available_platform'] = round($resumen['capital_available_platform'], $decimals);
		$resumen['capital_available_platform'] = $resumen['capital_available_platform']." ".$symbol;
		
		$resumen['balance_sheet'] = $capital_account_user + $capital_project_user + $capital_account_platform + $capital_project_platform;
		
		$resumen['balance_sheet'] *= $currency_user; 
		$resumen['balance_sheet'] = round($resumen['balance_sheet'], $decimals);
		$resumen['balance_sheet'] = $resumen['balance_sheet']." ".$symbol;
		
		// Si el usuario es de perfil administrador o plataforma
		if($this->session->userdata('logged_in')['profile_id'] == 1 || $this->session->userdata('logged_in')['profile_id'] == 2){
			// Retorno de todos los montos calculados
			return array(
				'resumen_general' => json_decode(json_encode($resumen), false),
				'resumen_plataforma' => json_decode(json_encode($resumen_platform), false),
				'resumen_usuarios' => json_decode(json_encode($resumen_users), false),
				'resumen_projects' => json_decode(json_encode($resumen_projects), false)
			);  // Esto retorna un arreglo de objetos
		}else{
			// Retorno de todos los montos calculados
			return array(
				'resumen_general' => json_decode(json_encode($resumen), false),
				'resumen_projects' => json_decode(json_encode($resumen_projects), false)
			);  // Esto retorna un arreglo de objetos
		}
        
    }
    
    // Caĺculamos los montos de capital disponible para retorno, el capital invertido, el capital de retorno, el depósito pendiente y 
    // el capital diferido para el resumen de plataforma.
	public function fondos_json_platform()
    {
		// Obtenemos el valor en dólares de las distintas divisas
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
		if($ct){
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		} else {
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=1d8edbe4f5d54857b1686c15befc4a85");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		}
		
		//~ // Valor de 1 btc en dólares
		//~ $get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		//~ $exchangeRates2 = json_decode($get2, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1btc = $exchangeRates2[0]['price_usd'];
		
		// Colectando los symbolos de todas las cryptomonedas soportadas por la plataforma de coinmarketcap
		$get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		$exchangeRates2 = json_decode($get2, true);
		$valor1anycoin = 0;
		$i = 0;
		$rate = $this->session->userdata('logged_in')['coin_iso'];
		$rates = array();
		foreach($exchangeRates2 as $divisa){
			if ($divisa['symbol'] == $rate){
				$i+=1;
				
				// Obtenemos el valor de la cryptomoneda del usuario en dólares
				$valor1anycoin = $divisa['price_usd'];
			}
			$rates[] = $divisa['symbol'];  // Colectamos los símbolos de todas las cryptomonedas
		}
		
		// Valor de 1 dólar en bolívares
		//~ $get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		//~ $exchangeRates3 = json_decode($get3, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1vef = $exchangeRates3['USD']['transferencia'];
		$valor1vef = $this->coin_rate;
		
		if (in_array($this->session->userdata('logged_in')['coin_iso'], $rates)) {
		
			$currency_user = 1/(float)$valor1anycoin;  // Tipo de moneda del usuario logueado
			
		} else if($this->session->userdata('logged_in')['coin_iso'] == 'VEF') {
		
			$currency_user = $valor1vef;  // Tipo de moneda del usuario logueado
		
		} else {
			
			$currency_user = $exchangeRates['rates'][$this->session->userdata('logged_in')['coin_iso']];  // Tipo de moneda del usuario logueado
			
		}
		
        
        $resumen_users = array();  // Para el resultado final (Listado de usuarios con sus respectivos resúmenes)
        
        list ($transactions, $project_transactions) = $this->MResumen->fondos_json_users();  // Listados de transacciones y transacciones por proyecto
        
        $ids_users = array();  // Para almacenar los ids de los usuarios que han registrado fondos
        
        // Colectamos los ids de los usuarios de las transacciones generales
        foreach($transactions as $fondo){
			
			if(!in_array($fondo->user_id, $ids_users)){
				if($fondo->user_id == 0){
					$ids_users[] = $fondo->user_id;
				}
			}
			
		}
		
		// Armamos una lista de fondos por usuario y lo almacenamos en el arreglo '$resumen_users'
		foreach($ids_users as $id_user){
			
			$resumen_user = array(
				'name' => '',
				'alias' => '',
				'username' => '',
				'capital_invested' => 0,
				'returned_capital' => 0,
				'retirement_capital_available' => 0,
				'pending_capital' => 0,
				'pending_entry' => 0,
				'pending_exit' => 0,
				'approved_capital' => 0,
			);
			
			foreach($transactions as $fondo){
				
				// Si no tiene usuario asociado en user_id lo tratamos como transacción de PLATAFORMA
				if($fondo->user_id == $id_user && $fondo->user_id == 0){
					
					// Conversión de cada monto a dólares
					$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
					
					// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
					if (in_array($currency, $rates)) {
						// Primero convertimos el valor de la cryptodivisa
						$valor1anycoin = 0;
						$i = 0;
						$rate = $currency;
						foreach($exchangeRates2 as $divisa){
							if ($divisa['symbol'] == $rate){
								$i+=1;
								
								// Obtenemos el valor de la cryptomoneda de la transacción en dólares
								$valor1anycoin = $divisa['price_usd'];
							}
						}
						
						$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
						
					}else if($currency == 'VEF'){
						
						$trans_usd = (float)$fondo->amount/(float)$valor1vef;
						
					}else{
						
						$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
						
					}
					
					// Si no tiene usuario asociado en user_id lo tratamos como transacción de PLATAFORMA
					if($fondo->user_id == 0){
						// Si el usuario es de perfil administrador o plataforma
						if($this->session->userdata('logged_in')['profile_id'] == 1 || $this->session->userdata('logged_in')['profile_id'] == 2){
							$resumen_user['name'] = 'PLATAFORMA';
							$resumen_user['alias'] = 'PLATAFORMA';
							$resumen_user['username'] = 'PLATAFORMA';
							if($fondo->status == 'waiting'){
								if($fondo->type == 'deposit'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_entry'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_exit'] += $trans_usd;
								}
							}
							if($fondo->status == 'approved'){
								$resumen_user['approved_capital'] += $trans_usd;
								if($fondo->type == 'invest'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'deposit'){
									if($fondo->project_id > 0){
										$resumen_user['capital_invested'] += $trans_usd;
									}
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'sell'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'profit'){
									$resumen_user['returned_capital'] += $trans_usd;
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'expense'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}
							}
						}else{
							$resumen_user['name'] = 'PLATAFORMA';
							$resumen_user['alias'] = 'PLATAFORMA';
							$resumen_user['username'] = 'PLATAFORMA';
							if($fondo->status == 'waiting'){
								if($fondo->type == 'deposit'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_entry'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_exit'] += $trans_usd;
								}
							}
							if($fondo->status == 'approved'){
								$resumen_user['approved_capital'] += $trans_usd;
								if($fondo->type == 'invest'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'deposit'){
									if($fondo->project_id > 0){
										$resumen_user['capital_invested'] += $trans_usd;
									}
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'sell'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'profit'){
									$resumen_user['returned_capital'] += $trans_usd;
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'expense'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}
							}
						}
					}
				}
				
			}  // Cierre del for each de transacciones
			
			$decimals = 2;
			if($this->session->userdata('logged_in')['coin_decimals'] != ""){
				$decimals = $this->session->userdata('logged_in')['coin_decimals'];
			}
			$symbol = $this->session->userdata('logged_in')['coin_symbol'];
			
			// Conversión de los montos a la divisa del usuario
			$resumen_user['capital_invested'] *= $currency_user; 
			$resumen_user['capital_invested'] = round($resumen_user['capital_invested'], $decimals);
			$resumen_user['capital_invested'] = $resumen_user['capital_invested']." ".$symbol;
			
			$resumen_user['returned_capital'] *= $currency_user; 
			$resumen_user['returned_capital'] = round($resumen_user['returned_capital'], $decimals);
			$resumen_user['returned_capital'] = $resumen_user['returned_capital']." ".$symbol;
			
			$resumen_user['retirement_capital_available'] *= $currency_user; 
			$resumen_user['retirement_capital_available'] = round($resumen_user['retirement_capital_available'], $decimals);
			$resumen_user['retirement_capital_available'] = $resumen_user['retirement_capital_available']." ".$symbol;
			
			$resumen_user['pending_capital'] *= $currency_user; 
			$resumen_user['pending_capital'] = round($resumen_user['pending_capital'], $decimals);
			$resumen_user['pending_capital'] = $resumen_user['pending_capital']." ".$symbol;
			
			$resumen_user['pending_entry'] *= $currency_user; 
			$resumen_user['pending_entry'] = round($resumen_user['pending_entry'], $decimals);
			$resumen_user['pending_entry'] = $resumen_user['pending_entry']." ".$symbol;
			
			$resumen_user['pending_exit'] *= $currency_user; 
			$resumen_user['pending_exit'] = round($resumen_user['pending_exit'], $decimals);
			$resumen_user['pending_exit'] = $resumen_user['pending_exit']." ".$symbol;
			
			$resumen_user['approved_capital'] *= $currency_user; 
			$resumen_user['approved_capital'] = round($resumen_user['approved_capital'], $decimals);
			$resumen_user['approved_capital'] = $resumen_user['approved_capital']." ".$symbol;
			
			$resumen_users[] = $resumen_user;
			
		}
		
        return json_decode(json_encode($resumen_users), false);  // Esto retorna un arreglo de objetos
    }
    
    // Caĺculamos los montos de capital disponible para retorno, el capital invertido, el capital de retorno, el depósito pendiente y 
    // el capital diferido para el resumen por usuario.
	public function fondos_json_users()
    {
		// Obtenemos el valor en dólares de las distintas divisas
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
		if($ct){
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		} else {
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=1d8edbe4f5d54857b1686c15befc4a85");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		}
		
		//~ // Valor de 1 btc en dólares
		//~ $get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		//~ $exchangeRates2 = json_decode($get2, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1btc = $exchangeRates2[0]['price_usd'];
		
		// Colectando los symbolos de todas las cryptomonedas soportadas por la plataforma de coinmarketcap
		$get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		$exchangeRates2 = json_decode($get2, true);
		$valor1anycoin = 0;
		$i = 0;
		$rate = $this->session->userdata('logged_in')['coin_iso'];
		$rates = array();
		foreach($exchangeRates2 as $divisa){
			if ($divisa['symbol'] == $rate){
				$i+=1;
				
				// Obtenemos el valor de la cryptomoneda del usuario en dólares
				$valor1anycoin = $divisa['price_usd'];
			}
			$rates[] = $divisa['symbol'];  // Colectamos los símbolos de todas las cryptomonedas
		}
		
		// Valor de 1 dólar en bolívares
		//~ $get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		//~ $exchangeRates3 = json_decode($get3, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1vef = $exchangeRates3['USD']['transferencia'];
		$valor1vef = $this->coin_rate;
		
		if (in_array($this->session->userdata('logged_in')['coin_iso'], $rates)) {
		
			$currency_user = 1/(float)$valor1anycoin;  // Tipo de moneda del usuario logueado
			
		} else if($this->session->userdata('logged_in')['coin_iso'] == 'VEF') {
		
			$currency_user = $valor1vef;  // Tipo de moneda del usuario logueado
		
		} else {
			
			$currency_user = $exchangeRates['rates'][$this->session->userdata('logged_in')['coin_iso']];  // Tipo de moneda del usuario logueado
			
		}
		
        
        $resumen_users = array();  // Para el resultado final (Listado de usuarios con sus respectivos resúmenes)
        
        list ($transactions, $project_transactions) = $this->MResumen->fondos_json_users();  // Listados de transacciones y transacciones por proyecto
        
        $ids_users = array();  // Para almacenar los ids de los usuarios que han registrado fondos
        
        // Colectamos los ids de los usuarios de las transacciones generales
        foreach($transactions as $fondo){
			
			if(!in_array($fondo->user_id, $ids_users)){
				if($fondo->user_id > 0){
					$ids_users[] = $fondo->user_id;
				}
			}
			
		}
		
		// Armamos una lista de fondos por usuario y lo almacenamos en el arreglo '$resumen_users'
		foreach($ids_users as $id_user){
			
			$resumen_user = array(
				'name' => '',
				'alias' => '',
				'username' => '',
				'capital_invested' => 0,
				'returned_capital' => 0,
				'retirement_capital_available' => 0,
				'pending_capital' => 0,
				'pending_entry' => 0,
				'pending_exit' => 0,
				'approved_capital' => 0,
			);
			
			foreach($transactions as $fondo){
				
				if($fondo->user_id == $id_user){
					
					// Conversión de cada monto a dólares
					$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
					
					// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
					if (in_array($currency, $rates)) {
						// Primero convertimos el valor de la cryptodivisa
						$valor1anycoin = 0;
						$i = 0;
						$rate = $currency;
						foreach($exchangeRates2 as $divisa){
							if ($divisa['symbol'] == $rate){
								$i+=1;
								
								// Obtenemos el valor de la cryptomoneda de la transacción en dólares
								$valor1anycoin = $divisa['price_usd'];
							}
						}
						
						$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
						
					}else if($currency == 'VEF'){
						
						$trans_usd = (float)$fondo->amount/(float)$valor1vef;
						
					}else{
						
						$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
						
					}
					
					// Si tiene usuario asociado en user_id incluimos la transacción en la suma
					if($fondo->user_id > 0){
						// Si el usuario es de perfil administrador o plataforma
						if($this->session->userdata('logged_in')['profile_id'] == 1 || $this->session->userdata('logged_in')['profile_id'] == 2){
							$resumen_user['name'] = $fondo->name;
							$resumen_user['alias'] = $fondo->alias;
							$resumen_user['username'] = $fondo->username;
							if($fondo->status == 'waiting'){
								if($fondo->type == 'deposit'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_entry'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_exit'] += $trans_usd;
								}
							}
							if($fondo->status == 'approved'){
								$resumen_user['approved_capital'] += $trans_usd;
								if($fondo->type == 'invest'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'deposit'){
									if($fondo->project_id > 0){
										$resumen_user['capital_invested'] += $trans_usd;
									}
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'sell'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'profit'){
									$resumen_user['returned_capital'] += $trans_usd;
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'expense'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}
							}
						}else{
							$resumen_user['name'] = $fondo->name;
							$resumen_user['alias'] = $fondo->alias;
							$resumen_user['username'] = $fondo->username;
							if($fondo->status == 'waiting'){
								if($fondo->type == 'deposit'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_entry'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['pending_capital'] += $trans_usd;
									$resumen_user['pending_exit'] += $trans_usd;
								}
							}
							if($fondo->status == 'approved'){
								$resumen_user['approved_capital'] += $trans_usd;
								if($fondo->type == 'invest'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'deposit'){
									if($fondo->project_id > 0){
										$resumen_user['capital_invested'] += $trans_usd;
									}
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'sell'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'profit'){
									$resumen_user['returned_capital'] += $trans_usd;
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'expense'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_user['retirement_capital_available'] += $trans_usd;
								}
							}
						}
					}
				}
				
			}  // Cierre del for each de transacciones
			
			$decimals = 2;
			if($this->session->userdata('logged_in')['coin_decimals'] != ""){
				$decimals = $this->session->userdata('logged_in')['coin_decimals'];
			}
			$symbol = $this->session->userdata('logged_in')['coin_symbol'];
			
			// Conversión de los montos a la divisa del usuario
			$resumen_user['capital_invested'] *= $currency_user; 
			$resumen_user['capital_invested'] = round($resumen_user['capital_invested'], $decimals);
			$resumen_user['capital_invested'] = $resumen_user['capital_invested']." ".$symbol;
			
			$resumen_user['returned_capital'] *= $currency_user; 
			$resumen_user['returned_capital'] = round($resumen_user['returned_capital'], $decimals);
			$resumen_user['returned_capital'] = $resumen_user['returned_capital']." ".$symbol;
			
			$resumen_user['retirement_capital_available'] *= $currency_user; 
			$resumen_user['retirement_capital_available'] = round($resumen_user['retirement_capital_available'], $decimals);
			$resumen_user['retirement_capital_available'] = $resumen_user['retirement_capital_available']." ".$symbol;
			
			$resumen_user['pending_capital'] *= $currency_user; 
			$resumen_user['pending_capital'] = round($resumen_user['pending_capital'], $decimals);
			$resumen_user['pending_capital'] = $resumen_user['pending_capital']." ".$symbol;
			
			$resumen_user['pending_entry'] *= $currency_user; 
			$resumen_user['pending_entry'] = round($resumen_user['pending_entry'], $decimals);
			$resumen_user['pending_entry'] = $resumen_user['pending_entry']." ".$symbol;
			
			$resumen_user['pending_exit'] *= $currency_user; 
			$resumen_user['pending_exit'] = round($resumen_user['pending_exit'], $decimals);
			$resumen_user['pending_exit'] = $resumen_user['pending_exit']." ".$symbol;
			
			$resumen_user['approved_capital'] *= $currency_user; 
			$resumen_user['approved_capital'] = round($resumen_user['approved_capital'], $decimals);
			$resumen_user['approved_capital'] = $resumen_user['approved_capital']." ".$symbol;
			
			$resumen_users[] = $resumen_user;
			
		}
		
        return json_decode(json_encode($resumen_users), false);  // Esto retorna un arreglo de objetos
    }
    
    // Caĺculamos los montos de capital disponible para retiro, el capital invertido y el capital de retorno.
	public function fondos_json_by_projects()
    {
		// Obtenemos el valor en dólares de las distintas divisas
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
		if($ct){
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		} else {
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=1d8edbe4f5d54857b1686c15befc4a85");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		}
		
		//~ // Valor de 1 btc en dólares
		//~ $get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		//~ $exchangeRates2 = json_decode($get2, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1btc = $exchangeRates2[0]['price_usd'];
		
		// Colectando los symbolos de todas las cryptomonedas soportadas por la plataforma de coinmarketcap
		$get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		$exchangeRates2 = json_decode($get2, true);
		$valor1anycoin = 0;
		$i = 0;
		$rate = $this->session->userdata('logged_in')['coin_iso'];
		$rates = array();
		foreach($exchangeRates2 as $divisa){
			if ($divisa['symbol'] == $rate){
				$i+=1;
				
				// Obtenemos el valor de la cryptomoneda del usuario en dólares
				$valor1anycoin = $divisa['price_usd'];
			}
			$rates[] = $divisa['symbol'];  // Colectamos los símbolos de todas las cryptomonedas
		}
		
		// Valor de 1 dólar en bolívares
		//~ $get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		//~ $exchangeRates3 = json_decode($get3, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1vef = $exchangeRates3['USD']['transferencia'];
		$valor1vef = $this->coin_rate;
		
		if (in_array($this->session->userdata('logged_in')['coin_iso'], $rates)) {
		
			$currency_user = 1/(float)$valor1anycoin;  // Tipo de moneda del usuario logueado
			
		} else if($this->session->userdata('logged_in')['coin_iso'] == 'VEF') {
		
			$currency_user = $valor1vef;  // Tipo de moneda del usuario logueado
		
		} else {
			
			$currency_user = $exchangeRates['rates'][$this->session->userdata('logged_in')['coin_iso']];  // Tipo de moneda del usuario logueado
			
		}		
        
        $resumen_projects = array();  // Para el resultado final (Listado de proyectos con sus respectivos resúmenes)
        
        $transactions = $this->MResumen->fondos_json_projects();  // Listados de transacciones
        
        $ids_projects = array();  // Para almacenar los ids de los proyectos que tienen fondos registrados
        
        // Colectamos los ids de los proyectos de las transacciones generales
        foreach($transactions as $fondo){
			
			if(!in_array($fondo->project_id, $ids_projects)){
				if($fondo->project_id != 0){
					$ids_projects[] = $fondo->project_id;
				}
			}
			
		}
		
		// Armamos una lista de fondos por proyecto y lo almacenamos en el arreglo '$resumen_project'
		foreach($ids_projects as $id_project){
			
			$resumen_project = array(
				'name' => '',
				'type' => '',
				'capital_invested' => 0,
				'returned_capital' => 0,
				'retirement_capital_available' => 0,
				'pending_capital' => 0,
				'pending_entry' => 0,
				'pending_exit' => 0,
				'approved_capital' => 0,
			);
			
			foreach($transactions as $fondo){
				
				if($fondo->project_id == $id_project){
					
					// Conversión de cada monto a dólares
					$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
					
					// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
					if (in_array($currency, $rates)) {
						// Primero convertimos el valor de la cryptodivisa
						$valor1anycoin = 0;
						$i = 0;
						$rate = $currency;
						foreach($exchangeRates2 as $divisa){
							if ($divisa['symbol'] == $rate){
								$i+=1;
								
								// Obtenemos el valor de la cryptomoneda de la transacción en dólares
								$valor1anycoin = $divisa['price_usd'];
							}
						}
						
						$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
						
					}else if($currency == 'VEF'){
						
						$trans_usd = (float)$fondo->amount/(float)$valor1vef;
						
					}else{
						
						$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
						
					}
					
					// Si no tiene proyecto asociado (project_id = 0) no sumamos el monto de la transacción actual
					if($fondo->project_id != 0){
						$resumen_project['name'] = $fondo->name;
						$resumen_project['type'] = $fondo->project_type;
						if($fondo->status == 'waiting'){
							if($fondo->type == 'deposit'){
								$resumen_project['pending_capital'] += $trans_usd;
								$resumen_project['pending_entry'] += $trans_usd;
							}else if($fondo->type == 'withdraw'){
								$resumen_project['pending_capital'] += $trans_usd;
								$resumen_project['pending_exit'] += $trans_usd;
							}
						}
						if($fondo->status == 'approved'){
							// Si el usuario es de perfil administrador o plataforma
							if($this->session->userdata('logged_in')['profile_id'] == 1 || $this->session->userdata('logged_in')['profile_id'] == 2){
								$resumen_project['approved_capital'] += $trans_usd;
								if($fondo->type == 'invest'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'deposit'){
									if($fondo->project_id > 0){
										$resumen_project['capital_invested'] += $trans_usd;
									}
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'sell'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'profit'){
									$resumen_project['returned_capital'] += $trans_usd;
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'expense'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}
							}else{
								$resumen_project['approved_capital'] += $trans_usd;
								if($fondo->type == 'invest'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'deposit'){
									if($fondo->project_id > 0){
										$resumen_project['capital_invested'] += $trans_usd;
									}
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'sell'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'profit'){
									$resumen_project['returned_capital'] += $trans_usd;
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'expense'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}else if($fondo->type == 'withdraw'){
									$resumen_project['retirement_capital_available'] += $trans_usd;
								}
							}
						}
					}
				}
				
			}  // Cierre del for each de transacciones
			
			$decimals = 2;
			if($this->session->userdata('logged_in')['coin_decimals'] != ""){
				$decimals = $this->session->userdata('logged_in')['coin_decimals'];
			}
			$symbol = $this->session->userdata('logged_in')['coin_symbol'];
			
			// Conversión de los montos a la divisa del usuario
			$resumen_project['capital_invested'] *= $currency_user; 
			$resumen_project['capital_invested'] = round($resumen_project['capital_invested'], $decimals);
			$resumen_project['capital_invested'] = $resumen_project['capital_invested']." ".$symbol;
			
			$resumen_project['returned_capital'] *= $currency_user; 
			$resumen_project['returned_capital'] = round($resumen_project['returned_capital'], $decimals);
			$resumen_project['returned_capital'] = $resumen_project['returned_capital']." ".$symbol;
			
			$resumen_project['retirement_capital_available'] *= $currency_user; 
			$resumen_project['retirement_capital_available'] = round($resumen_project['retirement_capital_available'], $decimals);
			$resumen_project['retirement_capital_available'] = $resumen_project['retirement_capital_available']." ".$symbol;
			
			$resumen_project['pending_capital'] *= $currency_user; 
			$resumen_project['pending_capital'] = round($resumen_project['pending_capital'], $decimals);
			$resumen_project['pending_capital'] = $resumen_project['pending_capital']." ".$symbol;
			
			$resumen_project['pending_entry'] *= $currency_user; 
			$resumen_project['pending_entry'] = round($resumen_project['pending_entry'], $decimals);
			$resumen_project['pending_entry'] = $resumen_project['pending_entry']." ".$symbol;
			
			$resumen_project['pending_exit'] *= $currency_user; 
			$resumen_project['pending_exit'] = round($resumen_project['pending_exit'], $decimals);
			$resumen_project['pending_exit'] = $resumen_project['pending_exit']." ".$symbol;
			
			$resumen_project['approved_capital'] *= $currency_user; 
			$resumen_project['approved_capital'] = round($resumen_project['approved_capital'], $decimals);
			$resumen_project['approved_capital'] = $resumen_project['approved_capital']." ".$symbol;
			
			$resumen_projects[] = $resumen_project;
			
		}
		
        return json_decode(json_encode($resumen_projects), false);  // Esto retorna un arreglo de objetos
    }
    
    // Caĺculamos los montos de capital disponible para retorno, invertido y de retorno
	public function fondos_json_projects()
    {
		// Obtenemos el valor en dólares de las distintas divisas
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
		if($ct){
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=65148900f9c2443ab8918accd8c51664");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		} else {
			$get = file_get_contents("https://openexchangerates.org/api/latest.json?app_id=1d8edbe4f5d54857b1686c15befc4a85");
			//~ // Se decodifica la respuesta JSON
			$exchangeRates = json_decode($get, true);
		}
		
		//~ // Valor de 1 btc en dólares
		//~ $get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		//~ $exchangeRates2 = json_decode($get2, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1btc = $exchangeRates2[0]['price_usd'];
		
		// Colectando los symbolos de todas las cryptomonedas soportadas por la plataforma de coinmarketcap
		$get2 = file_get_contents("https://api.coinmarketcap.com/v1/ticker/");
		$exchangeRates2 = json_decode($get2, true);
		$valor1anycoin = 0;
		$i = 0;
		$rate = $this->session->userdata('logged_in')['coin_iso'];
		$rates = array();
		foreach($exchangeRates2 as $divisa){
			if ($divisa['symbol'] == $rate){
				$i+=1;
				
				// Obtenemos el valor de la cryptomoneda del usuario en dólares
				$valor1anycoin = $divisa['price_usd'];
			}
			$rates[] = $divisa['symbol'];  // Colectamos los símbolos de todas las cryptomonedas
		}
		
		// Valor de 1 dólar en bolívares
		//~ $get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		//~ $exchangeRates3 = json_decode($get3, true);
		//~ // Con el segundo argumento lo decodificamos como un arreglo multidimensional y no como un arreglo de objetos
		//~ $valor1vef = $exchangeRates3['USD']['transferencia'];
		$valor1vef = $this->coin_rate;
		
		if (in_array($this->session->userdata('logged_in')['coin_iso'], $rates)) {
		
			$currency_user = 1/(float)$valor1anycoin;  // Tipo de moneda del usuario logueado
			
		} else if($this->session->userdata('logged_in')['coin_iso'] == 'VEF') {
		
			$currency_user = $valor1vef;  // Tipo de moneda del usuario logueado
		
		} else {
			
			$currency_user = $exchangeRates['rates'][$this->session->userdata('logged_in')['coin_iso']];  // Tipo de moneda del usuario logueado
			
		}
        
        $fondos_details = $this->MResumen->fondos_json_projects();  // Listado de fondos detallados
		
		$resumen = array(
			'capital_invested' => 0,
			'pending_entry' => 0,
			'pending_exit' => 0,
			'returned_capital' => 0,
			'retirement_capital_available' => 0,
			'capital_in_projects' => 0
		);
			
		$disponible = 0;
		foreach($fondos_details as $fondo){
				
			// Conversión de cada monto a dólares
			$currency = $fondo->coin_avr;  // Tipo de moneda de la transacción
			
			// Si el tipo de moneda de la transacción es alguna cryptomoneda (BTC, LTC, BCH, ect.) o Bolívares (VEF) hacemos la conversión usando una api más acorde
			if (in_array($currency, $rates)) {
				
				// Primero convertimos el valor de la cryptodivisa
				$valor1anycoin = 0;
				$i = 0;
				$rate = $currency;
				foreach($exchangeRates2 as $divisa){
					if ($divisa['symbol'] == $rate){
						$i+=1;
						
						// Obtenemos el valor de la cryptomoneda de la transacción en dólares
						$valor1anycoin = $divisa['price_usd'];
					}
				}
				
				$trans_usd = (float)$fondo->amount*(float)$valor1anycoin;
				
			}else if($currency == 'VEF'){
				
				$trans_usd = (float)$fondo->amount/(float)$valor1vef;
				
			}else{
				
				$trans_usd = (float)$fondo->amount/$exchangeRates['rates'][$currency];
				
			}
			
			
			// Si el usuario es de perfil administrador o plataforma
			if($this->session->userdata('logged_in')['profile_id'] == 1 || $this->session->userdata('logged_in')['profile_id'] == 2){
				// Capital en projecto
				if($fondo->project_id > 0){
					$resumen['capital_in_projects'] += $trans_usd;
				}
				// Resto de capitales
				if($fondo->status == 'waiting'){
					if($fondo->type == 'deposit'){
						$resumen['pending_entry'] += $trans_usd;
					}else if($fondo->type == 'withdraw'){
						$resumen['pending_exit'] += $trans_usd;
					}
				}
				if($fondo->status == 'approved'){
					if($fondo->type == 'invest'){
						$resumen['retirement_capital_available'] += $trans_usd;
					}else if($fondo->type == 'deposit'){
						if($fondo->project_id > 0){
							$resumen['capital_invested'] += $trans_usd;
						}
						$resumen['retirement_capital_available'] += $trans_usd;
					}else if($fondo->type == 'sell'){
						$resumen['capital_invested'] += $trans_usd;
						$resumen['retirement_capital_available'] += $trans_usd;
					}else if($fondo->type == 'profit'){
						$resumen['returned_capital'] += $trans_usd;
						if($fondo->user_id == 0){
							$resumen['retirement_capital_available'] += $trans_usd;
						}
					}else if($fondo->type == 'expense'){
						$resumen['retirement_capital_available'] += $trans_usd;
					}else if($fondo->type == 'withdraw'){
						$resumen['retirement_capital_available'] += $trans_usd;
					}
					
					$disponible += $trans_usd;
				}
			}else{
				// Capital en projecto
				if($fondo->project_id > 0){
					$resumen['capital_in_projects'] += $trans_usd;
				}
				// Resto de capitales
				if($fondo->status == 'waiting'){
					if($fondo->type == 'deposit'){
						$resumen['pending_entry'] += $trans_usd;
					}else if($fondo->type == 'withdraw'){
						$resumen['pending_exit'] += $trans_usd;
					}
				}
				if($fondo->status == 'approved'){
					if($fondo->type == 'invest'){
						$resumen['retirement_capital_available'] += $trans_usd;
					}else if($fondo->type == 'deposit'){
						if($fondo->project_id > 0){
							$resumen['capital_invested'] += $trans_usd;
						}
						if($fondo->project_id == 0){
							$resumen['retirement_capital_available'] += $trans_usd;
						}
					}else if($fondo->type == 'sell'){
						$resumen['capital_invested'] += $trans_usd;
						if($fondo->project_id == 0){
							//~ $resumen['retirement_capital_available'] += $trans_usd;
						}
					}else if($fondo->type == 'profit'){
						$resumen['returned_capital'] += $trans_usd;
						if($fondo->project_id == 0){
							//~ $resumen['retirement_capital_available'] += $trans_usd;
						}
					}else if($fondo->type == 'expense'){
						if($fondo->project_id == 0){
							//~ $resumen['retirement_capital_available'] += $trans_usd;
						}
					}else if($fondo->type == 'withdraw'){
						if($fondo->project_id == 0){
							$resumen['retirement_capital_available'] += $trans_usd;
						}
					}
				}
			}
			
		}  // Cierre del for each de transacciones
		
		$decimals = 2;
		if($this->session->userdata('logged_in')['coin_decimals'] != ""){
			$decimals = $this->session->userdata('logged_in')['coin_decimals'];
		}
		$symbol = $this->session->userdata('logged_in')['coin_symbol'];
		
		// Conversión de los montos a la divisa del usuario
		$resumen['capital_invested'] *= $currency_user; 
		$resumen['capital_invested'] = round($resumen['capital_invested'], $decimals);
		$resumen['capital_invested'] = $resumen['capital_invested']." ".$symbol;
		
		$resumen['returned_capital'] *= $currency_user; 
		$resumen['returned_capital'] = round($resumen['returned_capital'], $decimals);
		$resumen['returned_capital'] = $resumen['returned_capital']." ".$symbol;
		
		$resumen['retirement_capital_available'] *= $currency_user;
		$resumen['retirement_capital_available'] = round($resumen['retirement_capital_available'], $decimals);
		$resumen['retirement_capital_available'] = $resumen['retirement_capital_available']." ".$symbol;
		
		$resumen['pending_entry'] *= $currency_user;
		$resumen['pending_entry'] = round($resumen['pending_entry'], $decimals);
		$resumen['pending_entry'] = $resumen['pending_entry']." ".$symbol;
		
		$resumen['capital_in_projects'] *= $currency_user;
		$resumen['capital_in_projects'] = round($resumen['capital_in_projects'], $decimals);
		$resumen['capital_in_projects'] = $resumen['capital_in_projects']." ".$symbol;
		
        return json_decode(json_encode($resumen), false);  // Esto retorna un arreglo de objetos
    }
    
    
    // Método para actualizar el precio del dólar tomando como referencia la api de dolartoday
    public function load_rate(){
		
		$coin = 'USD';  // Moneda a convertir
		
		// Valor de 1 dólar en bolívares
		// Con el uso de @ evitamos la impresión forzosa de errores que hace file_get_contents()
		$ct = @file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
		
		if($ct){
			
			// Valor de 1 dólar en bolívares
			$get3 = file_get_contents("https://s3.amazonaws.com/dolartoday/data.json");
			$exchangeRates3 = json_decode($get3, true);
			$valor1vef = $exchangeRates3[$coin]['transferencia'];
			
			// Verificamos el valor del dólar
			if($valor1vef != 0 && $valor1vef != null && $valor1vef != ''){
			
				$data_reg = array(
					'coin' => $coin,
					'rate' => $valor1vef,
					'd_create' => date('Y-m-d')
				);
				
				$reg = $this->MCoinRate->insert($data_reg);				
				
			}else{
				
				// Cargamos un mensaje de error
				$this->coin_rate_message['type'] = 'error';
				$this->coin_rate_message['message'] = '1';
				
			}
			
		} else {
			
			$this->coin_rate_message['type'] = 'error';
			$this->coin_rate_message['message'] = '2';
		
		}
		
	}
	
	
	// Método para retornar el precio del dólar más actualizado en la base de datos
    public function show_rate(){
		
		// Consultamos los registros de las tasas
		$tasas = $this->MCoinRate->obtener();
		
		$valor_actual = 1;
		
		if(count($tasas) > 0){
		
			foreach($tasas as $tasa){
					
				$valor_actual = $tasa->rate;
				
			}
		
		}
			
		return number_format($valor_actual, 2, '.', '');
		
	}
	
}
