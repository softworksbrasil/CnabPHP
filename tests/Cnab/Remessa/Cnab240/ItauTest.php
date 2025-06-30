<?php

namespace Cnab\Tests\Remessa\Cnab240;

class ItauTest //extends \PHPUnit_Framework_TestCase
{
    public function testArquivoItau240PodeSerCriado()
    {
        $this->markTestIncomplete('A versão do layout foi alterada de 020 para 030, porém não ainda está em beta e não foram feitos testes para a nova versão');

        $codigoBanco = \Cnab\Banco::ITAU;
        $cnabFactory = new \Cnab\Factory();
        //$arquivo = $cnabFactory->createRemessa($codigoBanco, 'cnab240');
        $arquivo = new \Cnab\Remessa\Cnab240\Arquivo($codigoBanco);
        $arquivo->configure(array(
            'data_geracao' => new \DateTime('2020-05-26 01:02:03'),
            'data_gravacao' => new \DateTime('2020-05-26'),
            'nome_fantasia' => 'Nome Fantasia da sua empresa',
            'razao_social' => 'Razão social da sua empresa',
            'cnpj' => '11222333444455',
            'banco' => $codigoBanco, //código do banco
            'logradouro' => 'Logradouro da Sua empresa',
            'numero' => 'Número do endereço',
            'bairro' => 'Bairro da sua empresa',
            'cidade' => 'Cidade da sua empresa',
            'uf' => 'SP',
            'cep' => '00000111',
            'agencia' => '1234',
            'conta' => '123',
            'conta_dac' => '1',
        ));

        // você pode adicionar vários boletos em uma remessa
        $arquivo->insertDetalhe(array(
            'codigo_ocorrencia' => 1, // 1 = Entrada de título, futuramente poderemos ter uma constante
            'nosso_numero' => '12345',
            'numero_documento' => '12345678',
            'carteira' => '11',
            //'codigo_carteira' => \Cnab\CodigoCarteira::COBRANCA_SIMPLES,
            'especie' => \Cnab\Especie::ITAU_DIVERSOS, // Você pode consultar as especies Cnab\Especie::CEF_OUTROS, futuramente poderemos ter uma tabela na documentação
            'aceite' => 'Z', // "S" ou "N"
            //'registrado' => false,
            'valor' => 100.39, // Valor do boleto
            'instrucao1' => '', // 1 = Protestar com (Prazo) dias, 2 = Devolver após (Prazo) dias, futuramente poderemos ter uma constante
            'instrucao2' => '', // preenchido com zeros
            'sacado_razao_social' => 'Nome do cliente', // O Sacado é o cliente, preste atenção nos campos abaixo
            'sacado_tipo' => 'cnpj', //campo fixo, escreva 'cpf' (sim as letras cpf) se for pessoa fisica, cnpj se for pessoa juridica
            'sacado_cnpj' => '21.222.333.4444-55',
            'sacado_logradouro' => 'Logradouro do cliente',
            'sacado_bairro' => 'Bairro do cliente',
            'sacado_cep' => '00000-111',
            'sacado_cidade' => 'Cidade do cliente',
            'sacado_uf' => 'BA',
            'data_vencimento' => new \DateTime('2020-06-26'),
            'data_cadastro' => new \DateTime('2020-05-26'),
            'juros_de_um_dia' => 0.10, // Valor do juros de 1 dia'
            'data_desconto' => new \DateTime('2020-06-25'),
            'valor_desconto' => 10.0, // Valor do desconto
            'prazo' => 10, // prazo de dias para o cliente pagar após o vencimento
            'taxa_de_permanencia' => '0', //00 = Acata Comissão por Dia (recomendável), 51 Acata Condições de Cadastramento na CAIXA
            'mensagem' => 'Descrição do boleto',
            'data_multa' => new \DateTime('2020-06-27'), // data da multa
            'valor_multa' => 0.20, // valor da multa
            //'baixar_apos_dias' => 30,
            //'dias_iniciar_contagem_juros' => 1,
            'tipo_multa' => 'porcentagem',
        ));

        $texto = $arquivo->getText();
        $lines = explode("\r\n", trim($texto, "\r\n"));

        $this->assertEquals(7, count($lines));

        $headerArquivoText = $lines[0];
        $headerLoteText = $lines[1];
        $segmentoAText = $lines[2];
        $trailerLoteText = $lines[3];

        $asserts = array(
            'headerArquivo' => array(
                '1:3' => '341', // codigo_banco 
                '4:7' => '0000', // lote_servico 
                '8:8' => '0', // tipo_registro 
                '9:14' => '      ', // 
                '15:17' => '081', // Numero da versao do layout do arquivo
                '18:18' => '2', // codigo_inscricao  1 = CPF / 2 = CNPJ
                '19:32' => '11222333444455', // numero_inscricao 
                '33:52' => '                    ', // complemento de registro
                '53:57' => '01234', // agencia 
                '58:58' => ' ', // complemento de registro 
                '59:70' => '000000123456', // conta
                '71:71' => ' ', // complemento de registro
                '72:72' => '1', // dac daagencia/conta debitada
                '73:102' => 'Nome Fantasia da sua empresa  ', // nome_empresa 
                '103:132' => 'ITAU                          ', // nome_banco 
                '133:142' => '          ', // complemento de registro 
                '143:143' => '1', // codigo_remessa_retorno 1=REMESSA / 2=RETORNO
                '144:151' => '26052020', // data_geracao DDMMAAAA
                '152:157' => '010203', // hora_geracao HHMMSS
                '158:166' => '000000000', // complemento de registro
                '167:171' => '00000', // densidade_gravacao_arquivo 
                '172:240' => '                                                                     ', // complemento de registro
            ),
            'headerLote' => array(
                '1:3' => '341', // codigo_banco 
                '4:7' => '0001', // codigo do lote
                '8:8' => '1', // tipo_registro 
                '9:9' => 'C', // tipo_operacao C=CREDITO 
                '10:11' => '01', // tipo_pagamento 
                '12:13' => '00', // forma de pagamento
                '14:16' => '040', // versao_layout_lote 
                '17:17' => ' ', // complemento_de_registro_01 
                '18:18' => '2', // codigo_inscricao  1=CPF / 2=CNPJ
                '19:32' => '01122233344445', // numero_inscricao 
                '33:36' => '0123', // codigo_convenio 
                '37:52' => '                ', // complemento_de_registro_02
                '53:57' => '01234', // agencia 
                '58:58' => ' ', // complemento_de_registro_03
                '59:70' => '000000123456', // conta
                '71:71' => ' ', // complemento_de_registro_04
                '72:72' => ' ', // dac
                '73:102' => 'Nome Fantasia da sua empresa  ', // nome_empresa 
                '103:132' => 'finalidade pagamentos         ', // finalidade_lote 
                '133:142' => 'comp. hist', // historico_conta 
                '143:172' => 'rua longe pra caramba         ',// logradouro
                '173:177' => '00050',// numero_logradouro
                '178:192' => 'apto 1601      ',// complemento_endereco
                '193:212' => 'Londrina            ',// cidade
                '213:220' => '01234567',// cep
                '221:222' => 'PR',// estado
                '223:230' => '        ',// complemento_de_registro_05
                '231:240' => '          ',// ocorrencia
            ),
            'segmentoA' => array(
                '1:3' => '341', // codigo_banco 
                '4:7' => '0001', // lote_servico 
                '8:8' => '3', // tipo_registro 
                '9:13' => '00001', // numero_sequencial_lote 
                '14:14' => 'A', // codigo_segmento 
                '15:17' => '001', // tipo_movimento
                '18:20' => '018', // camara_centralizadora (018=TED / 700=DOC) 
                '21:23' => '748', // banco_favorecido 
                '24:43' => '07180000000000197726', // agencia_conta_favorecido
                '44:73' => 'Leandro munhoz favorecido     ', // nome_favorecido
                '74:93' => '00000000000000001   ', // numero_documento 
                '94:101' => '26062020', // data_pagamento 
                '102:104' => '009', // tipo_moeda 
                '105:112' => '01234567', // codigo_ispb
                '113:119' => '0000000', // complemento_de_registro_01 
                '120:134' => '000000012345678', // valor_pagamento
                '135:149' => '               ', // nosso_numero 
                '150:154' => '     ', // complemento_de_registro_02 
                '155:162' => '26062020', // data_real
                '163:177' => '000000012345678', // valor_real 
                '178:195' => 'Info hist cc      ', // finalidade_detalhe 
                '196:197' => '  ', // complemento_de_registro_03 
                '198:203' => '000000', // numero_documento_retorno 
                '204:217' => '00005122674990', // numero_inscricao_favorecido 
                '218:219' => '01', // finalidade_doc 
                '220:224' => 'ted  ', // finalidade_ted 
                '225:229' => '     ', // complemento_de_registro_03
                '230:230' => '0', // aviso
                '231:240' => 'ocorrencia', // ocorrencia 
            ),
            'trailerLote' => array(
                '1:3' => '341', // codigo_banco 
                '4:7' => '0001', // lote_servico 
                '8:8' => '5', // tipo_registro 
                '9:17' => '         ', // complemento_de_registro_01 
                '18:23' => '012345', // qtde_registro_lote 
                '24:41' => '000000000000123456', // valor_total_lote
                '42:59' => '000000000000000000', // complemento_de_registro_02
                '60:230' => '                                                                                                                                                                                    ', // complemento_de_registro_03
                '231:240' => 'ocorrencia', // ocorrencia
            ),
        );

        foreach ($asserts as $tipo => $campos) {
            $vname = "{$tipo}Text";
            foreach ($campos as $pos => $value) {
                $aux = explode(':', $pos);
                $start = $aux[0] - 1;
                $end = ($aux[1] - $aux[0]) + 1;
                $this->assertEquals($value, substr($$vname, $start, $end), "[ ] Campo $pos do $tipo");
            }
        }
    }
}
