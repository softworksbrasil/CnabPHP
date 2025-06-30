<?php

namespace Cnab\Remessa\Cnab240;

class Arquivo implements \Cnab\Remessa\IArquivo
{
    public $headerArquivo;
    public $headerLote;
    public $arrHeaderLote = array();
    public $detalhes = array();
    public $trailerLote;
    public $trailerArquivo;
    public $dados;
    public $numero_sequencial_lote;
    public $codigo_lote;

    private $_data_gravacao;
    private $_data_geracao;
    public $banco;
    public $codigo_banco;
    public $configuracao = array();
    public $layoutVersao;
    public $arrSegmento = array();
    const   QUEBRA_LINHA = "\r\n";

    public function __construct($codigo_banco, $layoutVersao = null)
    {
        $this->codigo_banco = $codigo_banco;
        $this->layoutVersao = $layoutVersao;
        $this->banco = \Cnab\Banco::getBanco($this->codigo_banco);
        //$this->data_gravacao = date('dmY');
    }

    public function configure(array $params)
    {
        $banco = \Cnab\Banco::getBanco($this->codigo_banco);
        $campos = array(
            'data_geracao', 'data_gravacao', 'nome_fantasia', 'razao_social', 'cnpj', 'logradouro', 'numero', 'bairro',
            'cidade', 'uf', 'cep',
        );

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $campos[] = 'codigo_cedente';
        }

        if($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $campos[] = 'codigo_convenio';
            $campos[] = 'codigo_carteira';
            $campos[] = 'variacao_carteira';
            $campos[] = 'conta_dv';
        }

        if ($this->codigo_banco == \Cnab\Banco::CEF || $this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $campos[] = 'agencia';
            $campos[] = 'agencia_dv';
            $campos[] = 'conta';
            $campos[] = 'operacao';
            $campos[] = 'numero_sequencial_arquivo';
        }



        foreach ($campos as $campo) {
            if (array_key_exists($campo, $params)) {
                if (strpos($campo, 'data_') === 0 && !($params[$campo] instanceof \DateTime)) {
                    throw new \Exception("config '$campo' need to be instance of DateTime");
                }
                $this->configuracao[$campo] = $params[$campo];
            } else {
                throw new \Exception('Configuração "'.$campo.'" need to be set');
            }
        }

        foreach ($campos as $key) {
            if (!array_key_exists($key, $params)) {
                throw new Exception('Configuração "'.$key.'" dont exists');
            }
        }

        $this->data_geracao = $this->configuracao['data_geracao'];
        $this->data_gravacao = $this->configuracao['data_gravacao'];

        $this->headerArquivo = new HeaderArquivo($this);
        $this->headerLote = new HeaderLote($this);
        $this->trailerLote = new TrailerLote($this);
        $this->trailerArquivo = new TrailerArquivo($this);

        $this->headerArquivo->codigo_banco = $this->banco['codigo_do_banco'];
        $this->headerArquivo->codigo_inscricao = 2;
        $this->headerArquivo->numero_inscricao = $this->prepareText($this->configuracao['cnpj'], '.-/');
        $this->headerArquivo->agencia = $this->configuracao['agencia'];
        $this->headerArquivo->agencia_dv = $this->configuracao['agencia_dv'];

        if($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $this->headerArquivo->codigo_convenio = $this->configuracao['codigo_convenio'];
            $this->headerArquivo->carteira = $this->configuracao['codigo_carteira'];
            $this->headerArquivo->variacao_carteira = $this->configuracao['variacao_carteira'];
            $this->headerArquivo->conta = $this->configuracao['conta'];
            $this->headerArquivo->conta_dv = $this->configuracao['conta_dv'];
        }

        if($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerArquivo->codigo_cedente = $this->configuracao['codigo_cedente'];
        }

        $this->headerArquivo->nome_empresa = $this->configuracao['nome_fantasia'];
        $this->headerArquivo->nome_banco = $banco['nome_do_banco'];
        $this->headerArquivo->codigo_remessa_retorno = 1;
        $this->headerArquivo->data_geracao = $this->configuracao['data_geracao'];
        $this->headerArquivo->hora_geracao = $this->configuracao['data_geracao'];
        $this->headerArquivo->numero_sequencial_arquivo = $this->configuracao['numero_sequencial_arquivo'];

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            if($this->layoutVersao === 'sigcb') {
                $this->headerArquivo->codigo_convenio = 0;
            } else {
                $codigoConvenio = sprintf(
                    '%04d%03d%08d',
                    $params['agencia'],
                    $params['operacao'],
                    $params['conta']
                );
                $codigoConvenio .= $this->mod11($codigoConvenio);
                $this->headerArquivo->codigo_convenio = $codigoConvenio;
            }
        }

        $this->headerLote->codigo_banco = $this->headerArquivo->codigo_banco;
        $this->headerLote->lote_servico = 1;
        $this->headerLote->tipo_operacao = 'R';
        $this->headerLote->codigo_inscricao = $this->headerArquivo->codigo_inscricao;
        $this->headerLote->numero_inscricao = $this->headerArquivo->numero_inscricao;
        $this->headerLote->agencia = $this->headerArquivo->agencia;
        $this->headerLote->agencia_dv = $this->headerArquivo->agencia_dv;


        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerLote->codigo_convenio = $this->headerArquivo->codigo_cedente;
            $this->headerLote->codigo_cedente = $this->headerArquivo->codigo_cedente;
        }

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $this->headerLote->codigo_convenio = $this->headerArquivo->codigo_convenio;
            $this->headerLote->carteira = $this->headerArquivo->carteira;
            $this->headerLote->variacao_carteira = $this->headerArquivo->variacao_carteira;
            $this->headerLote->conta = $this->headerArquivo->conta;
            $this->headerLote->conta_dv = $this->headerArquivo->conta_dv;
        }

        $this->headerLote->nome_empresa = $this->headerArquivo->nome_empresa;
        $this->headerLote->numero_sequencial_arquivo = $this->headerArquivo->numero_sequencial_arquivo;
        $this->headerLote->data_geracao = $this->headerArquivo->data_geracao;

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerLote->tipo_servico = 2;
        }

        $this->trailerLote->codigo_banco = $this->headerArquivo->codigo_banco;
        $this->trailerLote->lote_servico = $this->headerLote->lote_servico;

        $this->trailerArquivo->codigo_banco = $this->headerArquivo->codigo_banco;
    }

    public function mod11($num, $base = 9, $r = 0)
    {
        $soma = 0;
        $fator = 2;
        /* Separacao dos numeros */
        for ($i = strlen($num); $i > 0; --$i) {
            // pega cada numero isoladamente
            $numeros[$i] = substr($num, $i - 1, 1);
            // Efetua multiplicacao do numero pelo falor
            $parcial[$i] = $numeros[$i] * $fator;
            // Soma dos digitos
            $soma += $parcial[$i];
            if ($fator == $base) { // restaura fator de multiplicacao para 2
                $fator = 1;
            }
            ++$fator;
        }
        /* Calculo do modulo 11 */
        if ($r == 0) {
            $soma *= 10;
            $digito = $soma % 11;
            if ($digito == 10) {
                $digito = 0;
            }

            return $digito;
        } elseif ($r == 1) {
            $resto = $soma % 11;

            return $resto;
        }
    }

    public function insertDetalhe(array $boleto, $tipo = 'remessa')
    {
        $dateVencimento = $boleto['data_vencimento'] instanceof \DateTime ? $boleto['data_vencimento'] : new \DateTime($boleto['data_vencimento']);
        $dateCadastro = $boleto['data_cadastro'] instanceof \DateTime ? $boleto['data_cadastro'] : new \DateTime($boleto['data_cadastro']);
        $dateJurosMora = clone $dateVencimento;

        $detalhe = new Detalhe($this);

        // SEGMENTO P -------------------------------
        $detalhe->segmento_p->codigo_banco = $this->headerArquivo->codigo_banco;
        $detalhe->segmento_p->lote_servico = $this->headerLote->lote_servico;
        $detalhe->segmento_p->agencia = $this->headerArquivo->agencia;
        $detalhe->segmento_p->agencia_dv = $this->headerArquivo->agencia_dv;

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $detalhe->segmento_p->codigo_cedente = $this->headerArquivo->codigo_cedente;
        }

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $detalhe->segmento_p->conta = $this->headerArquivo->conta;
            $detalhe->segmento_p->conta_dv = $this->headerArquivo->conta_dv;
        }

        $detalhe->segmento_p->nosso_numero = $this->formatarNossoNumero($boleto['nosso_numero']);

        if($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            // Informar 1 – para carteira 11/12 na modalidade Simples; 2 ou 3 – para carteira 11/17 modalidade
            // Vinculada/Caucionada e carteira 31; 4 – para carteira 11/17 modalidade Descontada e carteira 51; e 7 – para
            // carteira 17 modalidade Simples.
            if($boleto['carteira'] == 17 && $boleto['codigo_carteira'] == \Cnab\CodigoCarteira::COBRANCA_SIMPLES) {
                $detalhe->segmento_p->codigo_carteira = 7;
            } else {
                $detalhe->segmento_p->codigo_carteira = $boleto['codigo_carteira'];
            }
        }

        if ($this->layoutVersao === 'sigcb' && $this->codigo_banco == \Cnab\Banco::CEF) {
            $detalhe->segmento_p->codigo_carteira = 1; // 1 = Cobrança Simples
            $detalhe->segmento_p->modalidade_carteira = $boleto['modalidade_carteira']; // 21 = (título Sem Registro emissão CAIXA)
        }

        $detalhe->segmento_p->forma_cadastramento = $boleto['registrado'] ? 1 : 2; // 1 = Com, 2 = Sem Registro
        if ($boleto['registrado'] && $this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerLote->tipo_servico = 1;
        }
        $detalhe->segmento_p->numero_documento = $boleto['numero_documento'];
        $detalhe->segmento_p->vencimento = $dateVencimento;
        $detalhe->segmento_p->valor_titulo = $boleto['valor'];
        $detalhe->segmento_p->especie = $boleto['especie']; // 4 = Duplicata serviço
        $detalhe->segmento_p->aceite = $boleto['aceite'];
        $detalhe->segmento_p->data_emissao = $dateCadastro;
        $detalhe->segmento_p->codigo_juros_mora = isset($boleto['codigo_juros_mora'])?$boleto['codigo_juros_mora']:1; // 1 = Por dia, 2= Taxa Mensal, 3= Isento

        if (!empty($boleto['dias_iniciar_contagem_juros']) && is_numeric($boleto['dias_iniciar_contagem_juros'])) {
            $dateJurosMora->modify("+{$boleto['dias_iniciar_contagem_juros']} days");
        } else {
            $dateJurosMora->modify('+1 day');
        }

        $detalhe->segmento_p->data_juros_mora = $dateJurosMora;

        $detalhe->segmento_p->valor_juros_mora = $boleto['juros_de_um_dia'];
        if ($boleto['valor_desconto'] > 0) {
            $detalhe->segmento_p->codigo_desconto_1 = 1; // valor fixo
            $detalhe->segmento_p->data_desconto_1 = $boleto['data_desconto'];
            $detalhe->segmento_p->valor_desconto_1 = $boleto['valor_desconto'];
        } else {
            $detalhe->segmento_p->codigo_desconto_1 = 0; // sem desconto
            $detalhe->segmento_p->data_desconto_1 = 0;
            $detalhe->segmento_p->valor_desconto_1 = 0;
        }
        $detalhe->segmento_p->valor_abatimento = 0;
        $detalhe->segmento_p->uso_empresa = $boleto['numero_documento'];

        if (!empty($boleto['codigo_protesto']) && !empty($boleto['prazo_protesto'])) {
            $detalhe->segmento_p->codigo_protesto = $boleto['codigo_protesto'];
            $detalhe->segmento_p->prazo_protesto = $boleto['prazo_protesto'];
        } else {
            $detalhe->segmento_p->codigo_protesto = 3; // 3 = Não protestar
            $detalhe->segmento_p->prazo_protesto = 0;
        }

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            // Campo não tratado pelo sistema. Informar 'zeros'.
            // O sistema considera a informação que foi cadastrada na
            // sua carteira junto ao Banco do Brasil.
            $detalhe->segmento_p->codigo_baixa = 0;
            $detalhe->segmento_p->prazo_baixa = 0;
        } else {
            if(isset($boleto['baixar_apos_dias'])) {
                if($boleto['baixar_apos_dias'] === false) {
                    // não baixar / devolver
                    $detalhe->segmento_p->codigo_baixa = 2;
                    $detalhe->segmento_p->prazo_baixa = 0;
                } else {
                    // baixa automática
                    $detalhe->segmento_p->codigo_baixa = 1;
                    $detalhe->segmento_p->prazo_baixa = $boleto['baixar_apos_dias'];
                }
            } else {
                $detalhe->segmento_p->codigo_baixa = 0;
                $detalhe->segmento_p->prazo_baixa = 0;
            }
        }

        if (array_key_exists('identificacao_distribuicao', $boleto)) {
            $detalhe->segmento_p->identificacao_distribuicao = $boleto['identificacao_distribuicao'];
        }

        if ($tipo == 'remessa') {
            $detalhe->segmento_p->codigo_ocorrencia = 1;
        } elseif ($tipo == 'baixa') {
            $detalhe->segmento_p->codigo_ocorrencia = 2;
        } else {
            throw new \Exception('Tipo de detalhe inválido: '.$tipo);
        }

        // SEGMENTO Q -------------------------------
        $detalhe->segmento_q->codigo_banco = $this->headerArquivo->codigo_banco;
        $detalhe->segmento_q->lote_servico = $this->headerLote->lote_servico;
        $detalhe->segmento_q->codigo_ocorrencia = $detalhe->segmento_p->codigo_ocorrencia;
        if (@$boleto['sacado_cnpj']) {
            $detalhe->segmento_q->sacado_codigo_inscricao = '2';
            $detalhe->segmento_q->sacado_numero_inscricao = $this->prepareText($boleto['sacado_cnpj'], '.-/');
            $detalhe->segmento_q->nome = $this->prepareText($boleto['sacado_razao_social']);
        } else {
            $detalhe->segmento_q->sacado_codigo_inscricao = '1';
            $detalhe->segmento_q->sacado_numero_inscricao = $this->prepareText($boleto['sacado_cpf'], '.-/');
            $detalhe->segmento_q->nome = $this->prepareText($boleto['sacado_nome']);
        }
        $detalhe->segmento_q->logradouro = $this->prepareText($boleto['sacado_logradouro']);
        $detalhe->segmento_q->bairro = $this->prepareText($boleto['sacado_bairro']);
        $detalhe->segmento_q->cep = str_replace('-', '', $boleto['sacado_cep']);
        $detalhe->segmento_q->cidade = $this->prepareText($boleto['sacado_cidade']);
        $detalhe->segmento_q->estado = $boleto['sacado_uf'];
        // se o titulo for de terceiro, o sacador é o terceiro
        $detalhe->segmento_q->sacador_codigo_inscricao = $this->headerArquivo->codigo_inscricao;
        $detalhe->segmento_q->sacador_numero_inscricao = $this->headerArquivo->numero_inscricao;
        $detalhe->segmento_q->sacador_nome = $this->headerArquivo->nome_empresa;

        // SEGMENTO R -------------------------------
        $detalhe->segmento_r->codigo_banco = $detalhe->segmento_p->codigo_banco;
        $detalhe->segmento_r->lote_servico = $detalhe->segmento_p->lote_servico;
        $detalhe->segmento_r->codigo_ocorrencia = $detalhe->segmento_p->codigo_ocorrencia;
        if ($boleto['valor_multa'] > 0) {
            $detalhe->segmento_r->codigo_multa = 1;
            $detalhe->segmento_r->valor_multa = $boleto['valor_multa'];
            $detalhe->segmento_r->data_multa = $boleto['data_multa'];
        } else {
            $detalhe->segmento_r->codigo_multa = 0;
            $detalhe->segmento_r->valor_multa = 0;
            $detalhe->segmento_r->data_multa = 0;
        }

        $this->detalhes[] = $detalhe;
    }

    public function headerArquivoSISPAG(array $params)
    {
        $banco = \Cnab\Banco::getBanco($this->codigo_banco);
        $campos = array(
            'data_geracao',
            'data_gravacao',
            'nome_fantasia',
            'razao_social',
            'codigo_inscricao',
            'numero_inscricao',
            'agencia',
            'conta',
            'conta_dac',
        );


        foreach ($campos as $campo) {
            if (array_key_exists($campo, $params)) {
                if (strpos($campo, 'data_') === 0 && !($params[$campo] instanceof \DateTime)) {
                    throw new \Exception("config '$campo' need to be instance of DateTime");
                }
                $this->configuracao[$campo] = $params[$campo];
            } else {
                throw new \Exception('Configuração "'.$campo.'" need to be set');
            }
        }

        foreach ($campos as $key) {
            if (!array_key_exists($key, $params)) {
                throw new Exception('Configuração "'.$key.'" dont exists');
            }
        }

        $this->data_geracao = $this->configuracao['data_geracao'];
        $this->data_gravacao = $this->configuracao['data_gravacao'];

        $this->headerArquivo = new HeaderArquivo($this);
        $this->headerLote = new HeaderLote($this);
        $this->trailerLote = new TrailerLote($this);
        $this->trailerArquivo = new TrailerArquivo($this);

        $this->headerArquivo->codigo_banco          = $this->banco['codigo_do_banco'];
        //$this->headerArquivo->lote_servico        = '0000';
        //$this->headerArquivo->tipo_registro       = 0;
        //$this->headerArquivo->numero_versao_layout_arquivo = '081';
        $this->headerArquivo->codigo_inscricao      = $this->configuracao['codigo_inscricao'];
        $this->headerArquivo->numero_inscricao      = $this->prepareText($this->configuracao['numero_inscricao'], '.-/');
        $this->headerArquivo->agencia               = $this->configuracao['agencia'];
        $this->headerArquivo->conta                 = $this->configuracao['conta'];
        $this->headerArquivo->conta_dac             = $this->configuracao['conta_dac'];
        $this->headerArquivo->nome_fantasia         = $this->configuracao['nome_fantasia'];
        $this->headerArquivo->nome_do_banco         = $banco['nome_do_banco'];
        $this->headerArquivo->codigo_remessa_retorno= 1;
        $this->headerArquivo->data_geracao          = $this->configuracao['data_geracao'];
        $this->headerArquivo->hora_geracao          = $this->configuracao['data_geracao'];
        //$this->headerArquivo->densidade_gravacao_arquivo = 0;

        $this->codigo_lote = 0;
        $this->trailerArquivo->codigo_banco = $this->headerArquivo->codigo_banco;
        $this->trailerArquivo->total_qtde_registros = 1;
        $this->trailerLote->qtde_registro_lote = 0;

        $this->dados = $this->headerArquivo->getEncoded().self::QUEBRA_LINHA;
    }

    public function headerLoteSISPAG(array $params, $tipo = 'remessa')
    {
        $campos = array(
            'logradouro',
            'numero_logradouro',
            'complemento_endereco',
            'bairro',
            'cidade',
            'estado',
            'cep',
            'codigo_do_lote',
            'tipo_pagamento',
            'forma_pagamento',
            'identificacao_lancamento',
            'finalidade_lote',
            'historico_conta',
        );


        foreach ($campos as $campo) {
            if (array_key_exists($campo, $params)) {
                if (strpos($campo, 'data_') === 0 && !($params[$campo] instanceof \DateTime)) {
                    throw new \Exception("config '$campo' need to be instance of DateTime");
                }
                $this->configuracao[$campo] = $params[$campo];
            } else {
                throw new \Exception('Configuração "'.$campo.'" need to be set');
            }
        }

        foreach ($campos as $key) {
            if (!array_key_exists($key, $params)) {
                throw new Exception('Configuração "'.$key.'" dont exists');
            }
        }

        // verifica pela forma_pagamento selecionada qual a versão do layout e qual segmento
        // '040' - Pagamentos através de cheque, OP, DOC, TED e crédito em conta corrente
        // '030' - Liquidação de títulos (bloquetos) em cobrança no Itaú e em outros Bancos
        $arr_forma_j = array('30','31');
        $arr_forma_o = array('13','19','91'); //21
        $arr_forma_n = array('16','17','18','21','22','25','27','35');
        if(in_array($this->configuracao['forma_pagamento'], $arr_forma_j))
        {
            $versao_layout = '030';
            $this->arrSegmento = array ('J');
        }
        elseif(in_array($this->configuracao['forma_pagamento'], $arr_forma_o))
        {
            $versao_layout = '030';
            $this->arrSegmento = array ('O');
        }
        elseif(in_array($this->configuracao['forma_pagamento'], $arr_forma_n))
        {
            $versao_layout = '030';
            $this->arrSegmento = array ('N');
        }
        else
        {
            $versao_layout = '040';
            $this->arrSegmento = array ('A');
        }

        $this->headerLote = new HeaderLote($this);

        $this->headerLote->codigo_banco = $this->headerArquivo->codigo_banco;

        $this->codigo_lote++;
        $this->headerLote->codigo_do_lote = $this->codigo_lote;// = $this->configuracao['codigo_do_lote']; // É sequencial, iniciando-se em 0001. Todos os pagamentos de um lote devem ter o mesmo "Código de Lote".
        // $this->headerLote->tipo_registro = 1;
        // $this->headerLote->tipo_operacao = 'C';
        // if( $this->configuracao['tipo_pagamento'] == 20 && /* se tratar de lote para financiamento de Bens e Serviços (COMPROR/FINABS) ou Desconto de NPR (Crédito Rural) */
        //     ($this->configuracao['forma_pagamento'] == '01' || $this->configuracao['forma_pagamento'] == '03'
        //     || $this->configuracao['forma_pagamento'] == '06' || $this->configuracao['forma_pagamento'] == '07'
        //     || $this->configuracao['forma_pagamento'] == '41' || $this->configuracao['forma_pagamento'] == '43' ))
        // {
        //     $this->headerLote->tipo_operacao = 'F';
        // }
        $this->headerLote->tipo_pagamento           = $this->configuracao['tipo_pagamento'];
        $this->headerLote->forma_pagamento          = $this->configuracao['forma_pagamento'];
        $this->headerLote->versao_layout_lote       = $versao_layout;
        $this->headerLote->codigo_inscricao         = $this->headerArquivo->codigo_inscricao;
        $this->headerLote->numero_inscricao         = $this->headerArquivo->numero_inscricao;
        $this->headerLote->identificacao_lancamento = $this->configuracao['identificacao_lancamento'];
        $this->headerLote->agencia                  = $this->headerArquivo->agencia;
        $this->headerLote->conta                    = $this->headerArquivo->conta;
        $this->headerLote->conta_dac                = $this->headerArquivo->conta_dac;
        $this->headerLote->nome_fantasia            = $this->headerArquivo->nome_fantasia;
        $this->headerLote->finalidade_lote          = $this->configuracao['finalidade_lote'];
        $this->headerLote->historico_conta          = $this->configuracao['historico_conta'];
        $this->headerLote->logradouro               = $this->configuracao['logradouro'];
        $this->headerLote->numero_logradouro        = $this->configuracao['numero_logradouro'];
        $this->headerLote->complemento_endereco     = $this->configuracao['complemento_endereco'];
        $this->headerLote->cidade                   = $this->configuracao['cidade'];
        $this->headerLote->cep                      = $this->configuracao['cep'];
        $this->headerLote->estado                   = $this->configuracao['estado'];
        //$this->headerLote->ocorrencia = '';

        $this->trailerArquivo->total_qtde_registros++;

        $this->trailerLote->codigo_banco = $this->headerArquivo->codigo_banco;
        $this->trailerLote->codigo_do_lote = $this->codigo_lote-1;
        if( $this->trailerLote->qtde_registro_lote > 0 )
        {
            $this->trailerLote->ocorrencia = '';
            $this->trailerLote->qtde_registro_lote++;
            $this->dados .= $this->trailerLote->getEncoded().self::QUEBRA_LINHA;
            $this->trailerArquivo->total_qtde_registros++;
            $this->trailerArquivo->total_qtde_lotes++;
        }
        else
        {
            $this->trailerArquivo->total_qtde_lotes = 1;
        }

        $this->numero_sequencial_lote = 1;
        $this->trailerLote->qtde_registro_lote = 1;
        $this->trailerLote->valor_total_lote = 0;

        //$this->arrHeaderLote[] = $this->headerLote;
        $this->dados .= $this->headerLote->getEncoded().self::QUEBRA_LINHA;
    }

    public function valido(array $sispag)
    {
        $valido = false;

        $detalhe = new Detalhe($this, $this->arrSegmento);

        // SEGMENTO A -------------------------------
        if( is_int(array_search('A', $this->arrSegmento)) )
        {
            if(isset($sispag['tipo_movimento']) && isset($sispag['camara_centralizadora']) && isset($sispag['banco_favorecido'])
                && isset($sispag['agencia_conta_favorecido']) && isset($sispag['nome_favorecido']) && isset($sispag['seu_numero'])
                && isset($sispag['data_pagamento']) && isset($sispag['codigo_ispb']) && isset($sispag['valor_pagamento'])
                && isset($sispag['finalidade_detalhe']) && isset($sispag['numero_inscricao_favorecido'])
                && isset($sispag['finalidade_doc']) && isset($sispag['finalidade_ted']) && isset($sispag['aviso']))
            {
                $valido = true;
            }
        }
        // SEGMENTO J -------------------------------
        if( is_int(array_search('J', $this->arrSegmento)) )
        {
            //converter codigo de barras nos campos
            $cod_barra = $this->formatarCodigoDeBarras($sispag['codigo_de_barras']);

            if(!isset($cod_barra['banco_favorecido']) || empty($cod_barra['banco_favorecido']) )
            {
                file_put_contents('/var/www/html/gsat2/tmp/log_sispag_erro.txt',$texto_log);
            }

            if(isset($sispag['tipo_movimento']) && isset($sispag['codigo_de_barras'])
                && isset($cod_barra['banco_favorecido']) && isset($cod_barra['moeda']) && isset($cod_barra['dv'])
                && isset($cod_barra['vencimento']) && isset($cod_barra['valor']) && isset($cod_barra['campo_livre'])
                && isset($sispag['nome_favorecido']) && isset($sispag['data_vencimento']) && isset($sispag['valor_titulo'])
                && isset($sispag['descontos']) && isset($sispag['acrescimos']) && isset($sispag['data_pagamento'])
                && isset($sispag['valor_pagamento']) && isset($sispag['seu_numero'])
                && isset($sispag['numero_inscricao_favorecido']))
            {
                $valido = true;
            }
        }
        // SEGMENTO O -------------------------------
        if( is_int(array_search('O', $this->arrSegmento)) )
        {
            //converter codigo de barras nos campos
            $cod_barra = $this->formatarCodigoDeBarras($sispag['codigo_de_barras']);

            if(isset($sispag['tipo_movimento']) && isset($sispag['codigo_de_barras']) && isset($sispag['nome_favorecido'])
            && isset($sispag['data_vencimento']) && isset($sispag['data_pagamento']) && isset($sispag['nota_fiscal'])
            && isset($sispag['valor_pagamento']) && isset($sispag['seu_numero']) && isset($cod_barra['codigo_completo']) )
            {
                $valido = true;
            }
        }
        // SEGMENTO N -------------------------------
        if( is_int(array_search('N', $this->arrSegmento)) )
        {
            if(isset($sispag['tipo_movimento']) && isset($sispag['codigo_de_barras']) && isset($sispag['nome_favorecido'])
                && isset($sispag['data_vencimento']) && isset($sispag['data_pagamento']) && isset($sispag['nota_fiscal'])
                && isset($sispag['valor_pagamento']) && isset($sispag['seu_numero']) )
            {
                $valido = true;
            }
        }

        return $valido;
    }

    public function insertDetalheSISPAG(array $sispag, $tipo = 'remessa')
    {
        $detalhe = new Detalhe($this, $this->arrSegmento);

        // SEGMENTO A -------------------------------
        if( is_int(array_search('A', $this->arrSegmento)) )
        {
            $detalhe->segmento_a->codigo_banco                = $this->headerArquivo->codigo_banco;
            $detalhe->segmento_a->codigo_do_lote              = $this->codigo_lote;//$this->headerLote->codigo_do_lote;
            //$detalhe->segmento_a->tipo_registro             = '3';
            //$detalhe->segmento_a->numero_sequencial_lote    = $sispag['numero_sequencial_lote'];
            $detalhe->segmento_a->numero_sequencial_lote = $this->numero_sequencial_lote++;
            //$detalhe->segmento_a->codigo_segmento           = 'A';
            $detalhe->segmento_a->tipo_movimento              = $sispag['tipo_movimento'];
            $detalhe->segmento_a->camara_centralizadora       = $sispag['camara_centralizadora'];
            $detalhe->segmento_a->banco_favorecido            = $sispag['banco_favorecido'];
            $detalhe->segmento_a->agencia_conta_favorecido    = $sispag['agencia_conta_favorecido'];
            $detalhe->segmento_a->nome_favorecido             = $sispag['nome_favorecido'];
            $detalhe->segmento_a->seu_numero                  = $sispag['seu_numero'];
            $detalhe->segmento_a->data_pagamento              = $sispag['data_pagamento'] instanceof \DateTime ? $sispag['data_pagamento'] : new \DateTime($sispag['data_pagamento']);
            //$detalhe->segmento_a->tipo_moeda                = '009';
            $detalhe->segmento_a->codigo_ispb                 = $sispag['codigo_ispb'];
            $detalhe->segmento_a->valor_pagamento             = $sispag['valor_pagamento'];
            $detalhe->segmento_a->nosso_numero                = ''; // consta apenas no retorno
            //$detalhe->segmento_a->data_real                   = $sispag['data_pagamento'] instanceof \DateTime ? $sispag['data_pagamento'] : new \DateTime($sispag['data_pagamento']); // consta apenas no retorno
            $detalhe->segmento_a->data_real                   = "00000000"; // consta apenas no retorno
            $detalhe->segmento_a->valor_real                  = 0; // consta apenas no retorno
            $detalhe->segmento_a->finalidade_detalhe          = $sispag['finalidade_detalhe'];
            $detalhe->segmento_a->numero_documento_retorno    = 0; // consta apenas no retorno
            $detalhe->segmento_a->numero_inscricao_favorecido = $this->prepareText($sispag['numero_inscricao_favorecido'], '.-/');
            $detalhe->segmento_a->finalidade_doc              = $sispag['finalidade_doc'];
            $detalhe->segmento_a->finalidade_ted              = $sispag['finalidade_ted'];
            $detalhe->segmento_a->aviso                       = $sispag['aviso'];
            //$detalhe->segmento_a->ocorrencia = ''; // consta apenas no retorno
        }
        // SEGMENTO J -------------------------------
        if( is_int(array_search('J', $this->arrSegmento)) )
        {
            //converter codigo de barras nos campos
            $cod_barra = $this->formatarCodigoDeBarras($sispag['codigo_de_barras']);

            if(!isset($cod_barra['banco_favorecido']) || empty($cod_barra['banco_favorecido']) )
            {
                file_put_contents('/var/www/html/gsat2/tmp/log_sispag_erro.txt',$texto_log);
            }

            $detalhe->segmento_j->codigo_banco      = $this->headerArquivo->codigo_banco;
            $detalhe->segmento_j->codigo_do_lote    = $this->codigo_lote;//$this->headerLote->codigo_do_lote;
            //$detalhe->segmento_j->tipo_registro   = '3';
            //$detalhe->segmento_j->numero_sequencial_lote = $sispag['numero_sequencial_lote'];
            $detalhe->segmento_j->numero_sequencial_lote = $this->numero_sequencial_lote++;
            //$detalhe->segmento_j->codigo_segmento = 'J';
            $detalhe->segmento_j->tipo_movimento    = $sispag['tipo_movimento'];
            $detalhe->segmento_j->banco_favorecido  = $cod_barra['banco_favorecido'];
            $detalhe->segmento_j->moeda             = $cod_barra['moeda'];
            $detalhe->segmento_j->dv                = $cod_barra['dv'];
            $detalhe->segmento_j->vencimento        = $cod_barra['vencimento'];
            $detalhe->segmento_j->valor             = $cod_barra['valor'];
            $detalhe->segmento_j->campo_livre       = $cod_barra['campo_livre'];
            $detalhe->segmento_j->nome_favorecido   = $sispag['nome_favorecido'];
            $detalhe->segmento_j->data_vencimento   = $sispag['data_vencimento'] instanceof \DateTime ? $sispag['data_vencimento'] : new \DateTime($sispag['data_vencimento']);
            $detalhe->segmento_j->valor_titulo      = $sispag['valor_titulo'];
            $detalhe->segmento_j->descontos         = $sispag['descontos'];
            $detalhe->segmento_j->acrescimos        = $sispag['acrescimos'];
            $detalhe->segmento_j->data_pagamento    = $sispag['data_pagamento'] instanceof \DateTime ? $sispag['data_pagamento'] : new \DateTime($sispag['data_pagamento']);
            $detalhe->segmento_j->valor_pagamento   = $sispag['valor_pagamento'];
            $detalhe->segmento_j->seu_numero        = $sispag['seu_numero'];
            $detalhe->segmento_j->nosso_numero      = ''; // consta apenas no retorno
            //$detalhe->segmento_j->ocorrencia      = ''; // consta apenas no retorno

            if( $this->headerLote->forma_pagamento == '30' || $this->headerLote->forma_pagamento == '31' )
            {
                $detalhe->segmento_j52->codigo_banco                  = $this->headerArquivo->codigo_banco;
                $detalhe->segmento_j52->codigo_do_lote                = $this->codigo_lote;//$this->headerLote->codigo_do_lote;
                //$detalhe->segmento_j52->tipo_registro               = '3';
                //$detalhe->segmento_j52->numero_sequencial_lote      = $sispag['numero_sequencial_lote'];
                $detalhe->segmento_j52->numero_sequencial_lote        = $detalhe->segmento_j->numero_sequencial_lote;
                //$detalhe->segmento_j52->codigo_segmento             = 'J';
                $detalhe->segmento_j52->tipo_movimento                = $sispag['tipo_movimento'];
                //$detalhe->segmento_j52->codigo_registro             = '52';
                $detalhe->segmento_j52->codigo_inscricao_pagador      = $this->headerLote->codigo_inscricao;
                $detalhe->segmento_j52->numero_inscricao_pagador      = $this->headerLote->numero_inscricao;
                $detalhe->segmento_j52->nome_pagador                  = $this->headerLote->nome_fantasia;
                $detalhe->segmento_j52->codigo_inscricao_beneficiario = strlen($this->prepareText($sispag['numero_inscricao_favorecido'], '.-/')) != 14 ? '1' : '2';
                $detalhe->segmento_j52->numero_inscricao_beneficiario = $this->prepareText($sispag['numero_inscricao_favorecido'], '.-/');
                $detalhe->segmento_j52->nome_beneficiario             = $sispag['nome_favorecido'];
                $detalhe->segmento_j52->codigo_inscricao_sacador      = '0';
                $detalhe->segmento_j52->numero_inscricao_sacador      = '0';
                $detalhe->segmento_j52->nome_sacador                  = '';

                $this->trailerArquivo->total_qtde_registros++;
                $this->trailerLote->qtde_registro_lote++;
            }
        }
        // SEGMENTO O -------------------------------
        if( is_int(array_search('O', $this->arrSegmento)) )
        {
            //converter codigo de barras nos campos
            $cod_barra = $this->formatarCodigoDeBarras($sispag['codigo_de_barras']);

            $detalhe->segmento_o->codigo_banco     = $this->headerArquivo->codigo_banco;
            $detalhe->segmento_o->codigo_do_lote   = $this->codigo_lote;//$this->headerLote->codigo_do_lote;
            //$detalhe->segmento_o->tipo_registro  = '3';
            //$detalhe->segmento_o->numero_sequencial_lote = $sispag['numero_sequencial_lote'];
            $detalhe->segmento_o->numero_sequencial_lote = $this->numero_sequencial_lote++;
            //$detalhe->segmento_o->codigo_segmento = 'O';
            $detalhe->segmento_o->tipo_movimento   = $sispag['tipo_movimento'];
            $detalhe->segmento_o->codigo_de_barras = $cod_barra['codigo_completo'];
            $detalhe->segmento_o->nome_favorecido  = $sispag['nome_favorecido'];
            $detalhe->segmento_o->data_vencimento  = $sispag['data_vencimento'] instanceof \DateTime ? $sispag['data_vencimento'] : new \DateTime($sispag['data_vencimento']);
            //$detalhe->segmento_o->tipo_moeda       = 'REA'; //tipo da moeda REA ou 009
            $detalhe->segmento_o->quantidade_moeda = '0'; //Se a moeda não for real, colocar aqui o valor
            $detalhe->segmento_o->valor_pagamento  = $sispag['valor_pagamento'];
            $detalhe->segmento_o->data_pagamento   = $sispag['data_pagamento'] instanceof \DateTime ? $sispag['data_pagamento'] : new \DateTime($sispag['data_pagamento']);
            $detalhe->segmento_o->valor_pago       = '0'; // consta apenas no retorno
            $detalhe->segmento_o->nota_fiscal      = $sispag['nota_fiscal'];
            /* Campo de preenchimento obrigatório para pagamento na forma de GNRE-SP com código de receita 10009.9
            – Substituição Tributária por Operação. Para demais pagamentos de tributos com código de barras ou
            GNRE-SP com outros códigos de receita, este campo deverá ser preenchido com zeros ou brancos. */
            $detalhe->segmento_o->seu_numero       = $sispag['seu_numero'];
            $detalhe->segmento_o->nosso_numero     = ''; // consta apenas no retorno
            //$detalhe->segmento_o->ocorrencia     = ''; // consta apenas no retorno
        }
        // SEGMENTO N -------------------------------
        if( is_int(array_search('N', $this->arrSegmento)) )
        {
            //converter codigo de barras nos campos
            $cod_barra = null;
            if( isset($sispag['codigo_de_barras']) && $sispag['codigo_de_barras'] != '');
            {
                $cod_barra = $sispag['codigo_de_barras'];
            }

            $data_pagamento = $sispag['data_pagamento']->format('dmY');

            $detalhe->segmento_n->codigo_banco = $this->headerArquivo->codigo_banco;
            $detalhe->segmento_n->codigo_do_lote = $this->codigo_lote;//$this->headerLote->codigo_do_lote;
            //$detalhe->segmento_n->tipo_registro = '3';
            $detalhe->segmento_n->numero_sequencial_lote = $this->numero_sequencial_lote++;
            //$detalhe->segmento_n->codigo_segmento = 'N';
            $detalhe->segmento_n->tipo_movimento = $sispag['tipo_movimento'];
            $detalhe->segmento_n->dados_tributo = $this->dadosPagamento($this->configuracao['forma_pagamento'], $data_pagamento, $sispag['valor_pagamento'], $sispag['codigo_receita'], $sispag['identificador'], $cod_barra);
            $detalhe->segmento_n->seu_numero = $sispag['seu_numero'];
            $detalhe->segmento_n->nosso_numero = ''; // consta apenas no retorno
            //$detalhe->segmento_n->ocorrencia = ''; // consta apenas no retorno
        }

        //$this->detalhes[] = $detalhe;
        // $arr = $detalhe->listSegmento();
        // $segmentos = array_filter($arr);
        // foreach ($segmentos as $segmento)
        // {
        //     if($segmento != 'J52')
        //         $segmento->numero_sequencial_lote = $this->numero_sequencial_lote++;
        // }

        $this->trailerLote->qtde_registro_lote++;

        if (!$detalhe->validate()) {
            throw new \InvalidArgumentException($detalhe->last_error);
        }
        $this->dados .= $detalhe->getEncoded().self::QUEBRA_LINHA;

        $this->trailerArquivo->total_qtde_registros++;


        $this->trailerLote->valor_total_lote += $sispag['valor_pagamento'];
    }

    public function formatarNossoNumero($nossoNumero)
    {
        if(!$nossoNumero)
            return $nossoNumero;

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $codigo_convenio = $this->configuracao['codigo_convenio'];

            if(strlen($codigo_convenio) <= 4) {
                # Convênio de 4 digitos
                if(strlen($nossoNumero) > 7) {
                    throw new \InvalidArgumentException(
                        "Para número de convênio de 4 posições o nosso número deve ter no máximo 7 posições (sem o digito)"
                    );
                }
                $number = sprintf('%04d%07d', $codigo_convenio, $nossoNumero);
                return $number . $this->mod11($number);
            } elseif (strlen($codigo_convenio) <= 6) {
                # Convênio de 6 digitos
                if(strlen($nossoNumero) > 5) {
                    throw new \InvalidArgumentException(
                        "Para número de convênio de 6 posições o nosso número deve ter no máximo 5 posições (sem o digito)"
                    );
                }
                $number = sprintf('%06d%05d', $codigo_convenio, $nossoNumero);
                return $number . $this->mod11($number);
            } else {
                if(strlen($nossoNumero) > 10) {
                    throw new \InvalidArgumentException(
                        "Para número de convênio de 7 posições o nosso número deve ter no máximo 10 posições"
                    );
                }
                $number = sprintf('%07d%010d', $codigo_convenio, $nossoNumero);
                return $number;
            }
        }

        return $nossoNumero;
    }

    /**
     * Formata Codigo de barras e retorna os valores em um array com a key o nome dos campos utilizados no segmento
     * @param string $codigoDeBarras
     * @return array
     */
    public function formatarCodigoDeBarras($codigoDeBarras)
    {
        $arrDados = array();
        $arrDados['codigo_completo'] = $codigoDeBarras;
        $codigoDeBarras = str_replace(' ', '', $codigoDeBarras);
        $codigoDeBarras = str_replace('.', '', $codigoDeBarras);

        if( strlen(str_replace(' ', '', $codigoDeBarras)) == 47 ) //formatar boleto
        {
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -14, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -16, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -18, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, '.', -24, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -30, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -32, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, '.', -38, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -44, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -46, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, '.', -51, 0);
        }

        if( strlen(str_replace(' ', '', $codigoDeBarras)) == 48 )
        {
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -1, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -13, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -15, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -27, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -29, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -41, 0);
            $codigoDeBarras = substr_replace($codigoDeBarras, ' ', -43, 0);
        }

        if( strlen($codigoDeBarras) == 57 )
        {
            $campo = array();
            $dv = array();
            $campo['1'] = str_replace('.', '', substr($codigoDeBarras,0,10));
            $dv['1'] = substr($codigoDeBarras,11, 1);
            $campo['2'] = str_replace('.', '', substr($codigoDeBarras,13,11));
            $dv['2'] = substr($codigoDeBarras,25, 1);
            $campo['3'] = str_replace('.', '', substr($codigoDeBarras,27,11));
            $dv['3'] = substr($codigoDeBarras,39, 1);
            $restante = str_replace(' ', '', substr($codigoDeBarras,40));

            $codigoDeBarras_aux = $campo['1'].$campo['2'].$campo['3'].$restante;

            $codigoDeBarras = substr($codigoDeBarras_aux,0,4);
            $codigoDeBarras .= substr($codigoDeBarras_aux,29,1);
            $codigoDeBarras .= substr($codigoDeBarras_aux,30,4);
            $codigoDeBarras .= substr($codigoDeBarras_aux,34);
            $codigoDeBarras .= substr($codigoDeBarras_aux,4,25);

            $arrDados['codigo_completo']  = $codigoDeBarras;

            $arrDados['banco_favorecido'] = substr($codigoDeBarras,0,3);
            $arrDados['moeda']            = substr($codigoDeBarras,3,1);
            $arrDados['dv']               = substr($codigoDeBarras,4,1);
            $arrDados['vencimento']       = substr($codigoDeBarras,5,4);
            $arrDados['valor']            = substr($codigoDeBarras,9,8) . "." . substr($codigoDeBarras,17,2);
            $arrDados['campo_livre']      = substr($codigoDeBarras,19);
        }
        elseif( strlen($codigoDeBarras) == 54 )
        {
            $campo = array();
            $dv = array();
            $campo['1'] = str_replace('.', '', substr($codigoDeBarras,0,10));
            $dv['1'] = substr($codigoDeBarras,10, 1);
            $campo['2'] = str_replace('.', '', substr($codigoDeBarras,12,11));
            $dv['2'] = substr($codigoDeBarras,23, 1);
            $campo['3'] = str_replace('.', '', substr($codigoDeBarras,25,11));
            $dv['3'] = substr($codigoDeBarras,36, 1);
            $restante = str_replace(' ', '', substr($codigoDeBarras,38));

            $codigoDeBarras_aux = $campo['1'].$campo['2'].$campo['3'].$restante;

            $codigoDeBarras = substr($codigoDeBarras_aux,0,4);
            $codigoDeBarras .= substr($codigoDeBarras_aux,29,1);
            $codigoDeBarras .= substr($codigoDeBarras_aux,30,4);
            $codigoDeBarras .= substr($codigoDeBarras_aux,34);
            $codigoDeBarras .= substr($codigoDeBarras_aux,4,25);

            $arrDados['codigo_completo']  = $codigoDeBarras;

            $arrDados['banco_favorecido'] = substr($codigoDeBarras,0,3);
            $arrDados['moeda']            = substr($codigoDeBarras,3,1);
            $arrDados['dv']               = substr($codigoDeBarras,4,1);
            $arrDados['vencimento']       = substr($codigoDeBarras,5,4);
            $arrDados['valor']            = substr($codigoDeBarras,9,8) . "." . substr($codigoDeBarras,17,2);
            $arrDados['campo_livre']      = substr($codigoDeBarras,19);
        }
        elseif( strlen($codigoDeBarras) == 55 )
        {
            $campo = array();
            $dv = array();
            $campo['1'] = str_replace('.', '', substr($codigoDeBarras,0,11));
            $dv['1'] = substr($codigoDeBarras,12, 1);
            $campo['2'] = str_replace('.', '', substr($codigoDeBarras,14,11));
            $dv['2'] = substr($codigoDeBarras,26, 1);
            $campo['3'] = str_replace('.', '', substr($codigoDeBarras,28,11));
            $dv['3'] = substr($codigoDeBarras,40, 1);
            $campo['4'] = str_replace('.', '', substr($codigoDeBarras,42,11));
            $dv['4'] = substr($codigoDeBarras,54, 1);

        }
        else
        {
            $arrDados['id_produto']      = substr($codigoDeBarras,0,1);
            $arrDados['id_segmento']     = substr($codigoDeBarras,1,1); // 1=Prefeituras(IPTU) | 2=Saneamento | 3=Energia Elétrica e Gás | 4=Telecomunicações
            $arrDados['id_valor']        = substr($codigoDeBarras,2,1); // Identificação do valor real ou referência - 6 = Reais | 7 = Moeda Variável
            $arrDados['dv']              = substr($codigoDeBarras,3,1);
            $arrDados['valor']           = substr($codigoDeBarras,4,9).".".substr($codigoDeBarras,13,2);
            $arrDados['id_empresa']      = substr($codigoDeBarras,15,4); //Identificação da Empresa / Órgão
            $arrDados['campo_livre']     = substr($codigoDeBarras,19);
        }

        return $arrDados;
    }

    /**
     * Formata os dados de pagamento de acordo com a forma de pagamento selecionada
     * @param string $forma_pagamento (código)
     * @return string
     */
    public function dadosPagamento($forma_pagamento, $data_pagamento, $valor_pagamento, $codigo_receita, $identificador = null, $codigo_de_barras = null) //16,17,18,21,22,25,27,35
    {
        $retorno = '';

        $dados = [];

        switch($forma_pagamento)
        {
            case '16' : // DARF

                break;
            case '17' : // GPS

                break;
            case '18' : // DARF SIMPLES

                break;
            case '21' : // DARJ

                break;
            case '22' : // GARE

                break;
            case '25' : // IPVA
            case '27' : // DPVAT

                break;
            case '35' : // FGTS
                    $remuneracao_calculada = str_pad(str_replace('.', '',$valor_pagamento),14, '0', STR_PAD_LEFT);

                    $multiplicador = 1;
                    $soma = 0;
                    for( $i = strlen($remuneracao_calculada); $i > 0; $i--)
                    {
                        if( $i != 4)
                        {
                            $multiplicador = $multiplicador < 9 ? $multiplicador+1 : 2;
                            $mult = $remuneracao_calculada[$i-1] * $multiplicador;
                            $soma += $mult;
                        }

                    }
                    $dv1 = 11 - ($soma % 11);

                    //Se o $dv1 for igual a 0, 1, 10 ou 11, considerem DV = 1.
                    if($dv1 == 10 || $dv1 == 0 || $dv1 == 11)
                    {
                        $dv1 = 1;
                    }

                    $identificador_fgts = $remuneracao_calculada.$dv1;

                    $multiplicador = 1;
                    $soma = 0;
                    for( $i = strlen($identificador_fgts); $i > 0; $i--)
                    {
                        if( $i != 4)
                        {
                            $multiplicador = $multiplicador < 9 ? $multiplicador+1 : 2;
                            $mult = $identificador_fgts[$i-1] * $multiplicador;
                            $soma += $mult;
                        }

                    }
                    $dv2 = $soma % 11;

                    if($dv2 == 1)
                    {
                        $identificador_fgts .= '0';
                    }
                    else
                    {
                        $identificador_fgts .= $dv2;
                    }

                    $dados[1] = '11'; //9(02) => Tributo 11 = FGTS
                    $dados[2] = $codigo_receita; //9(04) => Código da receita
                    $dados[3] = $this->headerLote->codigo_inscricao == 2 ? '1' : '2'; //9(01) => 1 = CNPJ | 2 = CEI
                    $dados[4] = str_pad($this->headerLote->numero_inscricao,14, '0', STR_PAD_LEFT); //9(14) => CPF/CNPJ
                    $dados[5] = str_replace(' ', '',$codigo_de_barras); //X(48) => código de barras
                    $dados[6] = $identificador_fgts; //9(16) => Identificador FGTS
                    $dados[7] = '         '; //9(09) => Lacre de Conectividade Social
                    $dados[8] = '  '; //9(02) => Dígito do Lacre
                    $dados[9] = str_pad($this->headerLote->nome_fantasia,30, ' ', STR_PAD_LEFT); //X(30) => Nome Contribuinte
                    $dados[10] = $data_pagamento; //9(08) => DDMMAAAA
                    $dados[11] = $remuneracao_calculada; //9(12)V9(02) => valor pagamento
                    $dados[12] = '                              '; //X(30)  => brancos
                break;
        }

        foreach($dados as $dado)
        {
            $retorno .= $dado;
        }

        return $retorno;
    }

    public function listDetalhes()
    {
        return $this->detalhes;
    }

    private function prepareText($text, $remove = null)
    {
        $result = strtoupper($this->removeAccents(trim(html_entity_decode($text))));
        if ($remove) {
            $result = str_replace(str_split($remove), '', $result);
        }

        return $result;
    }

    private function removeAccents($string)
    {
        return preg_replace(
            array(
                    '/\xc3[\x80-\x85]/',
                    '/\xc3\x87/',
                    '/\xc3[\x88-\x8b]/',
                    '/\xc3[\x8c-\x8f]/',
                    '/\xc3([\x92-\x96]|\x98)/',
                    '/\xc3[\x99-\x9c]/',

                    '/\xc3[\xa0-\xa5]/',
                    '/\xc3\xa7/',
                    '/\xc3[\xa8-\xab]/',
                    '/\xc3[\xac-\xaf]/',
                    '/\xc3([\xb2-\xb6]|\xb8)/',
                    '/\xc3[\xb9-\xbc]/',
                    '/\xC2\xAA/',
                    '/\xC2\xBA/',
            ),
            str_split('ACEIOUaceiouao', 1),
            $this->isUtf8($string) ? $string : utf8_encode($string)
        );
    }

    private function isUtf8($string)
    {
        return preg_match('%^(?:
                 [\x09\x0A\x0D\x20-\x7E]
                | [\xC2-\xDF][\x80-\xBF]
                | \xE0[\xA0-\xBF][\x80-\xBF]
                | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
                | \xED[\x80-\x9F][\x80-\xBF]
                | \xF0[\x90-\xBF][\x80-\xBF]{2}
                | [\xF1-\xF3][\x80-\xBF]{3}
                | \xF4[\x80-\x8F][\x80-\xBF]{2}
                )*$%xs',
                $string
        );
    }

    public function getText()
    {
        $numero_sequencial_lote = 1;
        $qtde_registro_lote = 2; // header e trailer = 2
        $qtde_titulo_cobranca_simples = 0;
        $valor_total_titulo_simples = 0;

        // valida os dados
        if (!$this->headerArquivo->validate()) {
            throw new \InvalidArgumentException($this->headerArquivo->last_error);
        }

        if (!$this->headerLote->validate()) {
            throw new \InvalidArgumentException($this->headerLote->last_error);
        }

        $dados = $this->headerArquivo->getEncoded().self::QUEBRA_LINHA;
        $dados .= $this->headerLote->getEncoded().self::QUEBRA_LINHA;

        foreach ($this->detalhes as $detalhe) {
            ++$qtde_titulo_cobranca_simples;
            $valor_total_titulo_simples += $detalhe->segmento_p->valor_titulo;
            foreach ($detalhe->listSegmento() as $segmento) {
                ++$qtde_registro_lote;
                $segmento->numero_sequencial_lote = $numero_sequencial_lote++;
            }

            if (!$detalhe->validate()) {
                throw new \InvalidArgumentException($detalhe->last_error);
            }

            $dados .= $detalhe->getEncoded().self::QUEBRA_LINHA;
        }

        $this->trailerLote->qtde_registro_lote = $qtde_registro_lote;

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->trailerLote->qtde_titulo_cobranca_simples = $qtde_titulo_cobranca_simples;
            $this->trailerLote->valor_total_titulo_simples = $valor_total_titulo_simples;
            $this->trailerLote->qtde_titulo_cobranca_caucionada = 0;
            $this->trailerLote->valor_total_titulo_caucionada = 0;
            $this->trailerLote->qtde_titulo_cobranca_descontada = 0;
            $this->trailerLote->valor_total_titulo_descontada = 0;
        }

        $this->trailerArquivo->qtde_lotes = 1;
        $this->trailerArquivo->qtde_registros = $this->trailerLote->qtde_registro_lote + 2;

        if (!$this->trailerLote->validate()) {
            throw new \InvalidArgumentException($this->trailerLote->last_error);
        }

        if (!$this->trailerArquivo->validate()) {
            throw new \InvalidArgumentException($this->trailerArquivo->last_error);
        }

        $dados .= $this->trailerLote->getEncoded().self::QUEBRA_LINHA;
        $dados .= $this->trailerArquivo->getEncoded().self::QUEBRA_LINHA;

        return $dados;
    }

    public function getTextSISPAG()
    {
        //$numero_sequencial_lote = 1;
        //$qtde_titulo_cobranca_simples = 0;
        $valor_total_lote = 0;

        // valida os dados
        if (!$this->headerArquivo->validate()) {
            throw new \InvalidArgumentException($this->headerArquivo->last_error);
        }

        if (!$this->headerLote->validate()) {
            throw new \InvalidArgumentException($this->headerLote->last_error);
        }

        $this->trailerLote->ocorrencia = '';

        if (!$this->trailerLote->validate()) {
            throw new \InvalidArgumentException($this->trailerLote->last_error);
        }

        if (!$this->trailerArquivo->validate()) {
            throw new \InvalidArgumentException($this->trailerArquivo->last_error);
        }

        $this->trailerLote->qtde_registro_lote++;
        $this->trailerLote->codigo_do_lote = $this->codigo_lote++;
        $this->dados .= $this->trailerLote->getEncoded().self::QUEBRA_LINHA;
        $this->trailerArquivo->total_qtde_registros += 2;
        $this->dados .= $this->trailerArquivo->getEncoded().self::QUEBRA_LINHA;

        return $this->dados;
    }

    public function countDetalhes()
    {
        return count($this->detalhes);
    }

    public function save($filename)
    {
        $text = $this->getText();

        file_put_contents($filename, $text);

        return $filename;
    }

    public function saveSISPAG($filename)
    {
        $text = $this->getTextSISPAG();

        file_put_contents($filename, $text);

        return $filename;
    }
}
