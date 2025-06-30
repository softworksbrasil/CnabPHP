<?php

namespace Cnab\Remessa\Cnab240;

class Detalhe
{
    public $segmento_p;
    public $segmento_q;
    public $segmento_r;
    public $segmento_a;
    public $segmento_j;
    public $segmento_j52;
    public $segmento_o;
    public $segmento_n;

    public $last_error;

    public function __construct(\Cnab\Remessa\IArquivo $arquivo, array $segmentos = array() )
    {
        if( is_int(array_search('A', $segmentos)) )
        {
            $this->segmento_a = new SegmentoA($arquivo);
        }
        if( is_int(array_search('J', $segmentos)) )
        {
            $this->segmento_j = new SegmentoJ($arquivo);
            $this->segmento_j52 = new SegmentoJ52($arquivo);
        }
        if( is_int(array_search('N', $segmentos)) )
        {
            $this->segmento_n = new SegmentoN($arquivo);
        }
        if( is_int(array_search('O', $segmentos)) )
        {
            $this->segmento_o = new SegmentoO($arquivo);
        }
        if( is_int(array_search('P', $segmentos)) )
        {
            $this->segmento_p = new SegmentoP($arquivo);
        }
        if( is_int(array_search('Q', $segmentos)) )
        {
            $this->segmento_q = new SegmentoQ($arquivo);
        }
        if( is_int(array_search('R', $segmentos)) )
        {
            $this->segmento_r = new SegmentoR($arquivo);
        }
        
        if( count($segmentos) == 0 )
        {
            $this->segmento_p = new SegmentoP($arquivo);
            $this->segmento_q = new SegmentoQ($arquivo);
            $this->segmento_r = new SegmentoR($arquivo);
        }
    }

    public function validate()
    {
        $this->last_error = null;
        $arr = $this->listSegmento();
        $segmentos = array_filter($arr);
        foreach ($segmentos as $segmento) {
            if (!$segmento->validate()) {
                $this->last_error = get_class($segmento).': '.$segmento->last_error;
            }
        }

        return is_null($this->last_error);
    }

    /**
     * Lista todos os segmentos deste detalhe.
     *
     * @return array
     */
    public function listSegmento()
    {
        return array(
            $this->segmento_p,
            $this->segmento_q,
            $this->segmento_r,
            $this->segmento_a,
            $this->segmento_j,
            $this->segmento_j52,
            $this->segmento_o,
            $this->segmento_n,
        );
    }

    /**
     * Retorna todas as linhas destes detalhes.
     *
     * @return string
     */
    public function getEncoded()
    {
        $text = array();
        $arr = $this->listSegmento();
        $segmentos = array_filter($arr);
        foreach ($segmentos as $segmento) {
            $text[] = $segmento->getEncoded();
        }

        return implode(Arquivo::QUEBRA_LINHA, $text);
    }
}
