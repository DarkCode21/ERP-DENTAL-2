<?php

/* block/cabecera.html.twig */
class __TwigTemplate_881165de0cd53e230a1569be676630a521153237a4d8eee602b641cda6837cf7 extends Twig_Template
{
    private $source;

    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = array(
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "
<div id=\"encabezado-pagina\" class=\"encabezado-pagina\">
    <div class=\"title-report\">
        <h3 class=\"title-report\">
                ";
        // line 5
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "i18nt", array()), "trans", array(0 => twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "params", array()), "title", array())), "method"), "html", null, true);
        echo "
                title:";
        // line 6
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "i18n", array()), "trans", array(0 => "title"), "method"), "html", null, true);
        echo "
                ";
        // line 7
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "params", array()), "title", array()), "html", null, true);
        echo "
            </h3>
    </div>
    <div class=\"empresa-fecha\">
        <div class=\"datos-empresa\">
            ";
        // line 12
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["fsc"] ?? null), "params", array()), "title", array()), "html", null, true);
        echo "
            <span class=\"title-company\">";
        // line 13
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "company"), "method"), "html", null, true);
        echo ": </span>
            <span class=\"company\" >Nazca Networks</span>
        </div>
        <div class=\"datos-fecha\">
            <span class=\"title-date\">";
        // line 17
        echo twig_escape_filter($this->env, twig_get_attribute($this->env, $this->source, ($context["i18n"] ?? null), "trans", array(0 => "date"), "method"), "html", null, true);
        echo ":</span>
            <span class=\"date\">";
        // line 18
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "d/m/Y"), "html", null, true);
        echo "</span>
        </div>
    </div>
</div>";
    }

    public function getTemplateName()
    {
        return "block/cabecera.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  60 => 18,  56 => 17,  49 => 13,  45 => 12,  37 => 7,  33 => 6,  29 => 5,  23 => 1,);
    }

    public function getSourceContext()
    {
        return new Twig_Source("", "block/cabecera.html.twig", "E:\\Xamp\\htdocs\\facturascripts2018\\Plugins\\fsreports\\View\\Block\\cabecera.html.twig");
    }
}
