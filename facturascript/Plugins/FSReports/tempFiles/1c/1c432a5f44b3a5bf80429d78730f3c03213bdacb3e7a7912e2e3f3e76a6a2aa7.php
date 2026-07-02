<?php

/* ReportLibroIngresos.html.twig */
class __TwigTemplate_68f290b3b80227e8d88ab3b5009a6bdbc9fe4194cca5e13f72ffe55ee7952a60 extends Twig_Template
{
    private $source;

    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = array(
            'css' => array($this, 'block_css'),
            'body' => array($this, 'block_body'),
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "<html>
    <head>
        <meta charset=\"utf-8\">
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
        ";
        // line 5
        $this->displayBlock('css', $context, $blocks);
        // line 8
        echo "
    </head>
    ";
        // line 10
        $this->displayBlock('body', $context, $blocks);
        // line 70
        echo "            </html>";
    }

    // line 5
    public function block_css($context, array $blocks = array())
    {
        // line 6
        echo "            <link rel=\"stylesheet\" href=\"./Plugins/fsreports/View/CSS/";
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "params", array()), "css_file", array()), "html", null, true);
        echo ".css\" media=\"all\">
        ";
    }

    // line 10
    public function block_body($context, array $blocks = array())
    {
        // line 11
        echo "        ";
        // line 12
        echo "        
        ";
        // line 13
        $this->loadTemplate("block/cabecera.html.twig", "ReportLibroIngresos.html.twig", 13)->display($context);
        // line 14
        echo "        <div class=\"row\">
            <div >
                <table style=\"border-collapse: collapse;\">
                    <thead>
                        <tr>
                            <th class=\" columna document\">";
        // line 19
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "document"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna date\">";
        // line 20
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "date"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna cif\">";
        // line 21
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "cif/nif"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna customer\">";
        // line 22
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "customer"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna base\">";
        // line 23
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "Base"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna poriva\">";
        // line 24
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "%IVA"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna iva\">";
        // line 25
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "IVA"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna porre\">";
        // line 26
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "%RE"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna re\">";
        // line 27
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "RE"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna porirpf\">";
        // line 28
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "%IRPF"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna irpf\">";
        // line 29
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "IRPF"), "method"), "html", null, true);
        echo "</th>
                            <th class=\"columna total\">";
        // line 30
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "total"), "method"), "html", null, true);
        echo "</th>
                        </tr>
                    </thead>
                    <tbody>
                        ";
        // line 34
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "listHeaderDoc", array(), "method"));
        foreach ($context['_seq'] as $context["_key"] => $context["value"]) {
            // line 35
            echo "                            <tr class=\"contenido\">
                                <td class=\"\">&nbsp;&nbsp;";
            // line 36
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "idfactura", array()), "html", null, true);
            echo " &nbsp;&nbsp; ";
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "codigo", array()), "html", null, true);
            echo "</td>
                                <td class=\"\">";
            // line 37
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "fecha", array()), "d/m/Y"), "html", null, true);
            echo "</td>
                                <td class=\"\">";
            // line 38
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "cifnif", array()), "html", null, true);
            echo "</td>
                                <td class=\"\">";
            // line 39
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "nombrecliente", array()), "html", null, true);
            echo "</td>

                            </tr>
                            ";
            // line 42
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "listLinesDoc", array(0 => array("idfactura" => twig_get_attribute($this->env, $this->source, $context["value"], "idfactura", array()))), "method"));
            foreach ($context['_seq'] as $context["_key"] => $context["value2"]) {
                // line 43
                echo "                                <tr class=\"contenido valoresparciales columna\">
                                    <td colspan=\"4\" id=\"relleno\"></td>
                                    <td id=\"vbasep\" class=\"vbasep linea\">";
                // line 45
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "base", array()), "html", null, true);
                echo "</td>
                                    <td id=\"vporiva\" class=\"vporiva linea\">";
                // line 46
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "iva", array()), "html", null, true);
                echo "%</td>
                                    <td id=\"viva\" class=\"viva linea \">";
                // line 47
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "importe_iva", array()), "html", null, true);
                echo "</td>
                                    <td id=\"vpre\" class=\"vpre linea \">";
                // line 48
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "recargo", array()), "html", null, true);
                echo "%</td>
                                    <td id=\"vre\" class=\"vre linea \">";
                // line 49
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "importe_recargo", array()), "html", null, true);
                echo "</td>
                                    <td id=\"vpirpf\" class=\"vpirpf linea \">";
                // line 50
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "irpf", array()), "html", null, true);
                echo "%</td>
                                    <td id=\"virpf\" class=\"virpf linea\">";
                // line 51
                echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value2"], "importe_irpf", array()), "html", null, true);
                echo "</td>
                                    <td id=\"vtotal\" class=\"total linea\">";
                // line 52
                echo twig_escape_filter($this->env, (((twig_get_attribute($this->env, $this->source, $context["value2"], "base", array()) + twig_get_attribute($this->env, $this->source, $context["value2"], "importe_iva", array())) + twig_get_attribute($this->env, $this->source, $context["value2"], "importe_recargo", array())) + twig_get_attribute($this->env, $this->source, $context["value2"], "importe_irpf", array())), "html", null, true);
                echo "</td>
                                </tr>
                            ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['value2'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 55
            echo "                            <tr class=\"columna\">
                                <td colspan=\"4\"></td>
                                <td id=\"totalbase\" class=\"totalbase\">";
            // line 57
            echo twig_escape_filter($this->env, (((twig_get_attribute($this->env, $this->source, $context["value"], "total", array()) - twig_get_attribute($this->env, $this->source, $context["value"], "totaliva", array())) - twig_get_attribute($this->env, $this->source, $context["value"], "totalrecargo", array())) - twig_get_attribute($this->env, $this->source, $context["value"], "totalirpf", array())), "html", null, true);
            echo "</td>

                                <td id=\"totaliva\" class=\"totaliva\" colspan=\"2\">";
            // line 59
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "totaliva", array()), "html", null, true);
            echo "</td>

                                <td id=\"totalre\" class=\"totalre\" colspan=\"2\">";
            // line 61
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "totalrecargo", array()), "html", null, true);
            echo "</td>

                                <td id=\"totalirpf\" class=\"totalirpf\" colspan=\"2\">";
            // line 63
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "totalirpf", array()), "html", null, true);
            echo "</td>
                                <td id=\"totalfactura\" class=\"totalfactura columna\">";
            // line 64
            echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, $context["value"], "total", array()), "html", null, true);
            echo "</td>
                            </tr>
                        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['value'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 67
        echo "                    </tbody>
                </table>
            ";
    }

    public function getTemplateName()
    {
        return "ReportLibroIngresos.html.twig";
    }

    public function getDebugInfo()
    {
        return array (  225 => 67,  216 => 64,  212 => 63,  207 => 61,  202 => 59,  197 => 57,  193 => 55,  184 => 52,  180 => 51,  176 => 50,  172 => 49,  168 => 48,  164 => 47,  160 => 46,  156 => 45,  152 => 43,  148 => 42,  142 => 39,  138 => 38,  134 => 37,  128 => 36,  125 => 35,  121 => 34,  114 => 30,  110 => 29,  106 => 28,  102 => 27,  98 => 26,  94 => 25,  90 => 24,  86 => 23,  82 => 22,  78 => 21,  74 => 20,  70 => 19,  63 => 14,  61 => 13,  58 => 12,  56 => 11,  53 => 10,  46 => 6,  43 => 5,  39 => 70,  37 => 10,  33 => 8,  31 => 5,  25 => 1,);
    }

    public function getSourceContext()
    {
        return new Twig_Source("", "ReportLibroIngresos.html.twig", "E:\\Xamp\\htdocs\\facturascripts2018\\Plugins\\fsreports\\View\\ReportLibroIngresos.html.twig");
    }
}
