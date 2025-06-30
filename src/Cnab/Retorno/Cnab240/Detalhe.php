<?php

namespace Cnab\Retorno\Cnab240;

class Detalhe extends \Cnab\Format\Linha implements \Cnab\Retorno\IDetalhe
{
    public $codigo_banco;
    public $arquivo;

    public $segmento_t;
    public $segmento_u;
    public $segmento_w;
    public $segmento_a;
    public $segmento_j;
    public $segmento_o;

    public function __construct(\Cnab\Retorno\IArquivo $arquivo)
    {
        $this->codigo_banco = $arquivo->codigo_banco;
        $this->arquivo = $arquivo;
    }

    /**
     * Retorno se é para dar baixa no boleto.
     *
     * @return bool
     */
    public function isBaixa()
    {
        $codigo_movimento = $this->segmento_t->codigo_movimento;

        return self::isBaixaStatic($codigo_movimento);
    }

    public static function isBaixaStatic($codigo_movimento)
    {
        $tipo_baixa = array(6, 9, 17, 25);
        $codigo_movimento = (int) $codigo_movimento;
        if (in_array($codigo_movimento, $tipo_baixa)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retorno se é uma baixa rejeitada.
     *
     * @return bool
     */
    public function isBaixaRejeitada()
    {
        $tipo_baixa = array(3, 26, 30);
        $codigo_movimento = (int) $this->segmento_t->codigo_movimento;
        if (in_array($codigo_movimento, $tipo_baixa)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Identifica o tipo de detalhe, se por exemplo uma taxa de manutenção.
     *
     * @return int
     */
    public function getCodigo()
    {
        $codigo = null;

        if($this->segmento_t)
            $codigo = (int) $this->segmento_t->codigo_movimento;
        elseif($this->segmento_a)
            $codigo = (int) $this->segmento_a->seu_numero;
        elseif($this->segmento_j)
            $codigo = (int) $this->segmento_j->seu_numero;
        elseif($this->segmento_o)
            $codigo = (int) $this->segmento_o->seu_numero;

        return $codigo;
    }

    /**
     * Retorna o valor recebido em conta.
     *
     * @return float
     */
    public function getValorRecebido()
    {
        $valor_recebido = 0;

        if($this->segmento_u)
            $this->segmento_u->valor_liquido;
        
        return $valor_recebido;
    }

    /**
     * Retorna o valor do título.
     *
     * @return float
     */
    public function getValorTitulo()
    {
        $valor_titulo = 0;
        if($this->segmento_t)
            $valor_titulo = $this->segmento_t->valor_titulo;
        elseif($this->segmento_a)
            $valor_titulo = $this->segmento_a->valor_pagamento;
        elseif($this->segmento_j)
            $valor_titulo = $this->segmento_j->valor_titulo;
        elseif($this->segmento_o)
            $valor_titulo = $this->segmento_o->valor_pagamento;
        
        return $valor_titulo;
    }

    /**
     * Retorna o valor do pago.
     *
     * @return float
     */
    public function getValorPago()
    {
        $valor_pago = 0;
        if($this->segmento_u)
            $valor_pago = $this->segmento_u->valor_pago;
        elseif($this->segmento_a)
            $valor_pago = $this->segmento_a->valor_real;
        elseif($this->segmento_j)
            $valor_pago = $this->segmento_j->valor_pagamento;
        elseif($this->segmento_o)
            $valor_pago = $this->segmento_o->valor_pago;
        
        return $valor_pago;
    }

    /**
     * Retorna o valor da tarifa.
     *
     * @return float
     */
    public function getValorTarifa()
    {
        $valor_tarifa = 0;
        
        if($this->segmento_t)
            $valor_tarifa = $this->segmento_t->valor_tarifa;

        return $valor_tarifa;
    }

    /**
     * Retorna o valor do Imposto sobre operações financeiras.
     *
     * @return float
     */
    public function getValorIOF()
    {
        return $this->segmento_u->valor_iof;
    }

    /**
     * Retorna o valor dos descontos concedido (antes da emissão).
     *
     * @return Double;
     */
    public function getValorDesconto()
    {
        $valor_desconto = 0;
        if($this->segmento_u)
            $valor_desconto = $this->segmento_u->valor_desconto;
        elseif($this->segmento_j)
            $valor_desconto = $this->segmento_j->descontos;
        
        return $valor_desconto;
    }

    /**
     * Retorna o valor dos abatimentos concedidos (depois da emissão).
     *
     * @return float
     */
    public function getValorAbatimento()
    {
        //segmento_j o valor do abatimento está junto com o desconto
        $valor_abatimento = null;

        if($this->segmento_u)
            $valor_abatimento = $this->segmento_u->valor_abatimento;

        return $valor_abatimento;
    }

    /**
     * Retorna o valor de outras despesas.
     *
     * @return float
     */
    public function getValorOutrasDespesas()
    {
        return $this->segmento_u->valor_outras_despesas;
    }

    /**
     * Retorna o valor de outros creditos.
     *
     * @return float
     */
    public function getValorOutrosCreditos()
    {
        $outros_creditos = 0;

        if($this->segmento_u)
            $outros_creditos = $this->segmento_u->valor_outros_creditos;
        
        return $outros_creditos;
    }

    /**
     * Retorna o número do documento do boleto.
     *
     * @return string
     */
    public function getNumeroDocumento()
    {
        $numero_documento = '0';
        
        if($this->segmento_t)
        {
            $numero_documento = $this->segmento_t->numero_documento;
        }
        elseif($this->segmento_a)
        {
            $numero_documento = $this->segmento_a->numero_documento_retorno;
        }
        
        if (trim($numero_documento, '0') == '') {
            return;
        }

        return $numero_documento;
    }

    /**
     * Retorna o nosso número do boleto.
     *
     * @return string
     */
    public function getNossoNumero()
    {
        if($this->segmento_t)
        {
            $nossoNumero = $this->segmento_t->nosso_numero;
        }
        elseif($this->segmento_a)
        {
            $nossoNumero = $this->segmento_a->nosso_numero;
        }
        elseif($this->segmento_j)
        {
            $nossoNumero = $this->segmento_j->nosso_numero;
        }
        elseif($this->segmento_o)
        {
            $nossoNumero = $this->segmento_o->nosso_numero;
        }

        if ($this->codigo_banco == 1) {
            $nossoNumero = preg_replace(
                '/^'.strval($this->arquivo->getCodigoConvenio()).'/',
                '',
                $nossoNumero
            );
        }

        if (in_array($this->codigo_banco, array(\Cnab\Banco::SANTANDER))) {
            // retira o dv
            $nossoNumero = substr($nossoNumero, 0, -1);
        }

        return $nossoNumero;
    }

    /**
     * Retorna o numero do controle interno.
     *
     * @return seu_numero
     */
    public function getSeuNumero()
    {
        $seu_numero = '';

        if($this->segmento_a)
        {
            $seu_numero = $this->segmento_a->seu_numero;
        }
        elseif($this->segmento_j)
        {
            $seu_numero = $this->segmento_j->seu_numero;
        }
        elseif($this->segmento_o)
        {
            $seu_numero = $this->segmento_o->seu_numero;
        }

        return $seu_numero;
    }

    /**
     * Retorna o objeto \DateTime da data de vencimento do boleto.
     *
     * @return \DateTime
     */
    public function getDataVencimento()
    {
        if($this->segmento_t)
        {
            $data = $this->segmento_t->data_vencimento ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_t->data_vencimento)) : false;
        }
        elseif($this->segmento_j)
        {
            $data = $this->segmento_j->data_vencimento ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_j->data_vencimento)) : false;
        }
        elseif($this->segmento_o)
        {
            $data = $this->segmento_o->data_vencimento ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_o->data_vencimento)) : false;
        }
        elseif($this->segmento_a)
        {
            $data = null;
        }

        if ($data) {
            $data->setTime(0, 0, 0);
        }

        return $data;
    }

    /**
     * Retorna a data em que o dinheiro caiu na conta.
     *
     * @return \DateTime
     */
    public function getDataCredito()
    {
        if($this->segmento_u)
        {
            $data = $this->segmento_u->data_credito ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_u->data_credito)) : false;
        }
        elseif($this->segmento_a)
        {
            $data = $this->segmento_a->data_real ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_a->data_real)) : false;
        }
        elseif($this->segmento_j)
        {
            $data = $this->segmento_j->data_pagamento ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_j->data_pagamento)) : false;
        }
        elseif($this->segmento_o)
        {
            $data = $this->segmento_o->data_pagamento ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_o->data_pagamento)) : false;
        }
        else
        {
            $data = null;
        }

        if ($data) {
            $data->setTime(0, 0, 0);
        }


        return $data;
    }

    /**
     * Retorna o valor de juros e mora.
     */
    public function getValorMoraMulta()
    {
        $moraMulta = 0;
        
        if($this->segmento_u)
            $moraMulta = $this->segmento_u->valor_acrescimos;

        return $moraMulta;
    }

    /**
     * Retorna a data da ocorrencia, o dia do pagamento.
     *
     * @return \DateTime
     */
    public function getDataOcorrencia()
    {
        if($this->segmento_u)
        {
            $data = $this->segmento_u->data_ocorrencia ? \DateTime::createFromFormat('dmY', sprintf('%08d', $this->segmento_u->data_ocorrencia)) : false;

            if ($data) {
                $data->setTime(0, 0, 0);
            }
        }
        else
        {
            $data = null;
        }

        return $data;
    }

    /**
     * Retorna a(s) ocorrencia(s), o dia do pagamento.
     *
     * @return \DateTime
     */
    public function getOcorrencia()
    {
        $ocorrencia = '';

        if($this->segmento_a)
        {
            $segmento = $this->segmento_a;
        }
        elseif($this->segmento_j)
        {
            $segmento = $this->segmento_j;
        }
        elseif($this->segmento_o)
        {
            $segmento = $this->segmento_o;
        }

        if($segmento)
        {
            for( $i = 0; $i < strlen($segmento->ocorrencia); $i +=2)
            {
                $ocorrencia .= substr($segmento->ocorrencia,$i,2);
                if($i+2 < strlen($segmento->ocorrencia))
                    $ocorrencia .= '_';
            }
        }

        return $ocorrencia;
    }

    /**
     * Retorna o número da carteira do boleto.
     *
     * @return string
     */
    public function getCarteira()
    {
        if ($this->codigo_banco == 104) {
            /*
            É formado apenas o código da carteira
            Código da Carteira
            Código adotado pela FEBRABAN, para identificar a característica dos títulos dentro das modalidades de
            cobrança existentes no banco.
            ‘1’ = Cobrança Simples
            ‘3’ = Cobrança Caucionada
            ‘4’ = Cobrança Descontada
            O Código ‘1’ Cobrança Simples deve ser obrigatoriamente informado nas modalidades Cobrança Simples
            e Cobrança Rápida.
            */
            return;
        } elseif ($this->segmento_t->existField('carteira')) {
            return $this->segmento_t->carteira;
        } else {
            return;
        }
    }

    /**
     * Retorna o número da agencia do boleto.
     *
     * @return string
     */
    public function getAgencia()
    {
        return $this->segmento_t->agencia_mantenedora;
    }

    /**
     * Retorna o número da agencia do boleto.
     *
     * @return string
     */
    public function getAgenciaDv()
    {
        return $this->segmento_t->agencia_dv;
    }

    /**
     * Retorna a agencia cobradora.
     *
     * @return string
     */
    public function getAgenciaCobradora()
    {
        return $this->segmento_t->agencia_cobradora;
    }

    /**
     * Retorna a o dac da agencia cobradora.
     *
     * @return string
     */
    public function getAgenciaCobradoraDac()
    {
        return $this->segmento_t->agencia_cobradora_dac;
    }

    /**
     * Retorna o numero sequencial.
     *
     * @return Integer;
     */
    public function getNumeroSequencial()
    {
        return $this->segmento_t->numero_sequencial_lote;
    }

    /**
     * Retorna o nome do código.
     *
     * @return string
     */
    public function getCodigoNome()
    {
        $codigo = (int) $this->getCodigo();

        $table = array(
             2 => 'Entrada Confirmada',
             3 => 'Entrada Rejeitada',
             4 => 'Transferência de Carteira/Entrada',
             5 => 'Transferência de Carteira/Baixa',
             6 => 'Liquidação',
             9 => 'Baixa',
            12 => 'Confirmação Recebimento Instrução de Abatimento',
            13 => 'Confirmação Recebimento Instrução de Cancelamento Abatimento',
            14 => 'Confirmação Recebimento Instrução Alteração de Vencimento',
            17 => 'Liquidação Após Baixa ou Liquidação Título Não Registrado',
            19 => 'Confirmação Recebimento Instrução de Protesto',
            20 => 'Confirmação Recebimento Instrução de Sustação/Cancelamento de Protesto',
            23 => 'Remessa a Cartório (Aponte em Cartório)',
            24 => 'Retirada de Cartório e Manutenção em Carteira',
            25 => 'Protestado e Baixado (Baixa por Ter Sido Protestado)',
            26 => 'Instrução Rejeitada',
            27 => 'Confirmação do Pedido de Alteração de Outros Dados',
            28 => 'Débito de Tarifas/Custas',
            30 => 'Alteração de Dados Rejeitada',
            36 => 'Confirmação de envio de e-mail/SMS',
            37 => 'Envio de e-mail/SMS rejeitado',
            43 => 'Estorno de Protesto/Sustação',
            44 => 'Estorno de Baixa/Liquidação',
            45 => 'Alteração de dados',
            51 => 'Título DDA reconhecido pelo sacado',
            52 => 'Título DDA não reconhecido pelo sacado',
            53 => 'Título DDA recusado pela CIP',
        );

        if (array_key_exists($codigo, $table)) {
            return $table[$codigo];
        } else {
            return 'Desconhecido';
        }
    }

    /**
     * Retorna o código de liquidação, normalmente usado para 
     * saber onde o cliente efetuou o pagamento.
     *
     * @return string
     */
    public function getCodigoLiquidacao()
    {
        // @TODO: Resgatar o código de liquidação
        return;
    }

    /**
     * Retorna a descrição do código de liquidação, normalmente usado para 
     * saber onde o cliente efetuou o pagamento.
     *
     * @return string
     */
    public function getDescricaoLiquidacao()
    {
        // @TODO: Resgator descrição do código de liquidação
        return;
    }

    public function dump()
    {
        $dump = PHP_EOL;
        $dump .= '== SEGMENTO T ==';
        $dump .= PHP_EOL;
        $dump .= $this->segmento_t->dump();
        $dump .= '== SEGMENTO U ==';
        $dump .= PHP_EOL;
        $dump .= $this->segmento_u->dump();

        if ($this->segmento_w) {
            $dump .= '== SEGMENTO W ==';
            $dump .= PHP_EOL;
            $dump .= $this->segmento_w->dump();
        }

        return $dump;
    }

    public function isDDA()
    {
        // @TODO: implementar funçao isDDA no Cnab240
    }

    public function getAlegacaoPagador()
    {
        // @TODO: implementar funçao getAlegacaoPagador no Cnab240
    }
}
