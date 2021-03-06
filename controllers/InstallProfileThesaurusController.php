<?php

// Affiche les messages de débuggage
$DEBUG = false;
// Affiche plus d'informations à l'écran
$VERBOSE = false;

// Limitation en nb de lignes des traitements de fichier pour le débuggage
$limitation_fichier = 0;

// Désactivation de l'indexation pour la recherche
//define("__CA_DONT_DO_SEARCH_INDEXING__", true);

require_once(__CA_LIB_DIR__ . '/core/Configuration.php');
// Inclusions nécessaires des fichiers de providence
//require_once(__CA_LIB_DIR__.'/core/Db.php');
require_once(__CA_MODELS_DIR__."/ca_storage_locations.php");
require_once(__CA_MODELS_DIR__ . '/ca_objects.php');
require_once(__CA_MODELS_DIR__ . '/ca_occurrences.php');
require_once(__CA_MODELS_DIR__."/ca_entities.php");
require_once(__CA_MODELS_DIR__."/ca_users.php");
require_once(__CA_MODELS_DIR__."/ca_lists.php");
require_once(__CA_MODELS_DIR__."/ca_locales.php");
require_once(__CA_MODELS_DIR__."/ca_collections.php");
require_once(__CA_LIB_DIR__.'/core/Parsers/DelimitedDataParser.php');

require_once(__CA_BASE_DIR__.'/app/plugins/museesDeFrance/lib/migration_functionlib.php');



class InstallProfileThesaurusController extends ActionController
{
	# -------------------------------------------------------
	protected $opo_config; // plugin configuration file
	protected $opa_infos_campagnes_par_recolement_decennal;

	# -------------------------------------------------------
	#
	# -------------------------------------------------------
	public function __construct(&$po_request, &$po_response, $pa_view_paths = null)
	{
		parent::__construct($po_request, $po_response, $pa_view_paths);

		if (!$this->request->user->canDoAction('can_use_recolementsmf_plugin')) {
			$this->response->setRedirect($this->request->config->get('error_display_url') . '/n/3000?r=' . urlencode($this->request->getFullUrlPath()));
			return;
		}
		$ps_plugin_path = __CA_BASE_DIR__ . "/app/plugins/museesDeFrance";

		if (file_exists($ps_plugin_path . '/conf/local/museesDeFrance.conf')) {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/local/museesDeFrance.conf');
		} else {
			$this->opo_config = Configuration::load($ps_plugin_path . '/conf/museesDeFrance.conf');
		}
	}

    /****************************************************************
     * Fonction de traitement des fichiers de liste
     ****************************************************************/
    public function traiteFichierDMF($t_filename,$t_idno_prefix,$t_list_description,$nb_lignes_vides=0,$ligne_limite=0) {
        global $pn_locale_id, $VERBOSE, $DEBUG;

        $t_locale = new ca_locales();
        $pn_locale_id = $t_locale->loadLocaleByCode('fr_FR');		// default locale_id
        $t_list = new ca_lists();

        $vn_list_item_type_concept = $t_list->getItemIDFromList('list_item_types', 'concept');
        $vn_list_item_label_synonym = $t_list->getItemIDFromList('list_item_label_types', 'uf');
        $vn_place_other= $t_list->getItemIDFromList('places_types', 'other');

        $result= 0;
        $row = 1;
        $parent = array ();
        $nb_tab_pre=0;

        $explode_separator_array = array();
        $explode_separator_array[1]["separator"]=" = ";
        $explode_separator_array[1]["label_type"]=$vn_list_item_label_synonym;

        $t_filename = __CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/thesaurus/txt/".$t_filename;
        if (($handle = fopen($t_filename, "r")) !== FALSE) {
            if (!$vn_list_id=getListID($t_list,"dmf_".$t_idno_prefix,$t_list_description)) {
                print json_encode("Impossible de trouver la liste dmf_".$t_idno_prefix." !.\n");
                die();
            } else {
                //print "{ 'thesaurus' : 'Liste dmf_".$t_idno_prefix." : $vn_list_id'";
            }
            $contenu_fichier = file_get_contents($t_filename);
            $total=substr_count($contenu_fichier, "\n")+1;
            $contenu_fichier="";

            $data="";
            $parent_selected=0;

            while (($data = fgets($handle)) !== FALSE) {
                $libelle = str_replace("\t", "", $data);
                $libelle = str_replace("\r\n", "", $libelle);

                // comptage du nb de tabulation pour connaître le terme parent
                $nb_tab = substr_count($data,"\t");
                $row++;

                // Si aucune information n'est à afficher, on affiche une barre de progression
                //if ((!$DEBUG) && (!$VERBOSE)) {
                //    show_status($row, $total);

                if ($row % 5 == 0) {
                    $d = array('thesaurus' => "Liste dmf_".$t_idno_prefix , 'progress' => round(100*$row/$total,2));
                    echo json_encode($d) . PHP_EOL;
                    ob_flush();
                    flush();
                }

                //sleep(1);
                //print ",\n{ 'progression' : '".."'}";
                //}

                if (($row > $nb_lignes_vides + 1) && ($libelle !="")) {

                    if ($row == $ligne_limite) {
                        //print ",\n{ 'limite atteinte' : '".$ligne_limite."' }";
                        break;
                        //die();
                    }

                    // si plus d'une tabulation
                    if (($nb_tab_pre != $nb_tab) && ($nb_tab > 0)) {
                        $parent_selected=$parent[$nb_tab - 1];
                    } elseif ($nb_tab == 0) {
                        $parent_selected=0;
                    }

                    // débuggage
                    if ($DEBUG) print "(".$parent_selected.") ".$nb_tab." ".$libelle;

                    // insertion dans la liste
                    if ($vn_item_id=getItemID($t_list,$vn_list_id,$vn_list_item_type_concept,$t_idno_prefix."_".($row-$nb_lignes_vides),$libelle,"",1,0, $parent_selected, null, $explode_separator_array )) {
                        //if ($VERBOSE) print "LIST ITEM CREATED : ".$libelle."";
                    } else {
                        //print ",\n{ 'LIST ITEM CREATION FAILED' : '".$libelle."'}";
                        die();
                    }

                    //print $nb_tab_pre." ".$nb_tab." - parent :".$parent_selected." ".$lexutil;
                    // si au moins 1 tabulation, conservation de l'item pour l'appeler comme parent
                    // $vn_item_id=$nb_tab;
                    $parent[$nb_tab]=$vn_item_id;

                }

                $nb_tab_pre=$nb_tab;
            }
            fclose($handle);
            //if ($VERBOSE) { print "dmf_".$t_idno_prefix." treated.\n";}
            $d = array('thesaurus' => "Liste dmf_".$t_idno_prefix , 'progress' => 100);
            echo json_encode($d) . PHP_EOL;
            ob_flush();
            flush();

            $result = true;
            //print "\n}";
            //die();
        } else {
            //print "le fichier n'a pu être ouvert.";
            $result=false;
        }
        return $result;
    }


    /****************************************************************
     * Fonction de traitement du fichier de lieux
     ****************************************************************/
    private function traiteFichierLieuDMF($t_filename,$t_idno_prefix,$nb_lignes_vides=0,$ligne_limite=0) {
        global $pn_locale_id, $VERBOSE, $DEBUG;
        global $vn_list_item_type_concept,$vn_list_item_label_synonym,$vn_place_other;
        global $t_list;

        $result= 0;
        $row = 1;
        $parent = array ();
        $nb_tab_pre=0;

        $explode_separator_array = array();
        $explode_separator_array[1]["separator"]=" = ";
        $explode_separator_array[1]["label_type"]=$vn_list_item_label_synonym;

        print "traitement des lieux\n";
        print __CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/thesaurus/txt/".$t_filename."<br/>";die();
        if (($handle = fopen(__CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/thesaurus/txt/".$t_filename, "r")) !== FALSE) {
            $contenu_fichier = file_get_contents($t_filename);
            $total=substr_count($contenu_fichier, "\n");
            $contenu_fichier="";

            $data="";
            $parent_selected=1;

            while (($data = fgets($handle)) !== FALSE) {
                $libelle = str_replace("\t", "", $data);
                $libelle = str_replace("\r\n", "", $libelle);

                // comptage du nb de tabulation pour connaître le terme parent
                $nb_tab = substr_count($data,"\t");
                $row++;

                // Si aucune information n'est à afficher, on affiche une barre de progression
                //if ((!$DEBUG) && (!$VERBOSE)) {
                //    show_status($row, $total);
                //}

                if (($row > $nb_lignes_vides + 1) && ($libelle !="")) {

                    if ($row == $ligne_limite) {
                        print "limite atteinte : ".$ligne_limite." \n";
                        break;
                        //die();
                    }

                    // si plus d'une tabulation
                    if (($nb_tab_pre != $nb_tab) && ($nb_tab > 0)) {
                        $parent_selected=$parent[$nb_tab - 1];
                    } elseif ($nb_tab == 0) {
                        $parent_selected=1;
                    }

                    // débuggage
                    if ($DEBUG) print "(".$parent_selected.") ".$nb_tab." ".$libelle;

                    // insertion dans la liste
                    if ($vn_place_id=getPlaceID($libelle, $t_idno_prefix."_".($row-$nb_lignes_vides), $vn_place_other, $parent_selected, $explode_separator_array)) {
                    } else {
                        print "PLACE CREATION FAILED : ".$libelle." ";
                        die();
                    }

                    $parent[$nb_tab]=$vn_place_id;

                }

                $nb_tab_pre=$nb_tab;
            }
            fclose($handle);
            if ($VERBOSE) { print "dmf_".$t_idno_prefix." treated.\n";}
            $result = true;
        } else {
            print "le fichier n'a pu être ouvert.";
            $result=false;
        }
        return $result;
    }

	# -------------------------------------------------------
	public function Index()
	{
		//$this->view->setVar('campagnes', $this->opa_infos_campagnes);
        if(!is_file(__CA_BASE_DIR__."/install/profiles/xml/joconde-sans-thesaurus.xml")) {
            $this->view->setVar('joconde_available', "false");
        }
		$this->render('index_install_profile_thesaurus_html.php');
	}

	# -------------------------------------------------------
	public function Profile()
	{
        if(!is_file(__CA_BASE_DIR__."/install/profiles/xml/joconde-sans-thesaurus.xml")) {
            $this->view->setVar('joconde_available', "false");
            if(!copy(__CA_BASE_DIR__."/app/plugins/museesDeFrance/assets/profile/joconde-sans-thesaurus.xml",__CA_BASE_DIR__."/install/profiles/xml/joconde-sans-thesaurus.xml")) {
                $this->view->setVar('joconde_installed', "false");
            } else {
                $this->view->setVar('joconde_installed', "true");
            }
        } else {
            $this->view->setVar('joconde_available', "true");
        }

		$this->render('install_profile_html.php');
	}
	# -------------------------------------------------------
	public function Thesaurus()
	{
		$this->view->setVar('variable', "value");
		$this->render('install_thesaurus_html.php');
	}

    public function ThesaurusImportAjax()
    {
        global $limitation_fichier;
        //type octet-stream. make sure apache does not gzip this type, else it would get buffered
        header('Content-Type: text/octet-stream');
        header('Cache-Control: no-cache'); // recommended to prevent caching of event data.

        switch($_GET["thesaurus"]) {
            case "lexdomn" :
                $this->traiteFichierDMF("lexdomn-20100921.txt","lexdomn","DMF : Liste des domaines",16,$limitation_fichier);
                break;
            case "lextech" :
                $this->traiteFichierDMF("lextech-201009A.txt","lextech","DMF : Liste des techniques",5,$limitation_fichier);
                break;
            case "lexmateriaux" :
                $this->traiteFichierDMF("lextech-201009B.txt","lexmateriaux","DMF : Liste des matériaux",5,$limitation_fichier);
                break;
            case "lexautr" :
                $this->traiteFichierDMF("lexautr-201009.txt","lexautr","DMF : Liste des auteurs",8,$limitation_fichier);
                break;
            case "lexautrole" :
                $this->traiteFichierDMF("lexautrole-201009.txt","lexautrole","DMF : Liste des rôles des auteurs/exécutants",5,$limitation_fichier);
                break;
            case "lexdecv" :
                $this->traiteFichierDMF("lexdecv-201009A.txt","lexdecv","DMF : Liste des méthodes de collecte",5,$limitation_fichier);
                break;
            case "lexsite" :
                $this->traiteFichierDMF("lexdecv-201009B.txt","lexsite","DMF : Liste des méthodes de types de sites et lieux géographiques de découverte",5,$limitation_fichier);
                break;
            case "lexdeno" :
                $this->traiteFichierDMF("lexdeno-201009.txt","lexdeno","DMF : Liste des dénominations",4,$limitation_fichier);
                break;
            case "lexecol" :
                $this->traiteFichierDMF("lexepoq-201009.txt","lexepoq","DMF : Liste des époques / styles",4,$limitation_fichier);
                break;
            case "lexepoq" :
                $this->traiteFichierDMF("lexepoq-201009.txt","lexepoq","DMF : Liste des époques / styles",4,$limitation_fichier);
                break;
            case "lexgene" :
                $this->traiteFichierDMF("lexgene-201009.txt","lexgene","DMF : Liste des stades de création (genèse des oeuvres)",5,$limitation_fichier);
                break;
            case "lexinsc" :
                $this->traiteFichierDMF("lexperi-20100921.txt","lexperi","DMF : Liste des datations en siècle ou millénaire (périodes de création, d'exécution et d'utilisation)",5,$limitation_fichier);
                break;
            case "lexperi" :
                $this->traiteFichierDMF("lexperi-20100921.txt","lexperi","DMF : Liste des datations en siècle ou millénaire (périodes de création, d'exécution et d'utilisation)",5,$limitation_fichier);
                break;
            case "lexsrep" :
                $this->traiteFichierDMF("lexperi-20100921.txt","lexperi","DMF : Liste des datations en siècle ou millénaire (périodes de création, d'exécution et d'utilisation)",5,$limitation_fichier);
                break;
            case "lexstat" :
                $this->traiteFichierDMF("lexstat-201009.txt","lexstat","DMF : Liste des termes autorisés du statut juridique de l'objet",4,$limitation_fichier);
                break;
            case "lexutil" :
                $this->traiteFichierDMF("lexutil-201009.txt","lexutil","DMF : Liste des utilisations - destinations",5,$limitation_fichier);
                break;
            case "lexrepr" :
                $this->traiteFichierDMF("lexrepr-201203.txt","lexrepr","DMF : Liste des sujets représentés",5,$limitation_fichier);
                break;
            case "lexlieux" :
                $this->traiteFichierLieuDMF("txt/lexlieux-201009.txt","lexlieux",5,$limitation_fichier);
                break;
        }

        exit();
    }

    public function ThesaurusImport()
    {
        $this->view->setVar('thesaurus', $_GET["thesaurus"]);
        $this->render('install_thesaurus_import_html.php');
    }
	# -------------------------------------------------------
	# Sidebar info handler
	# -------------------------------------------------------
	public function Info($pa_parameters)
	{
		$this->view->setVar('variable', "value");
		return $this->render('widget_install_profile_thesaurus_html.php', true);
	}
}

?>