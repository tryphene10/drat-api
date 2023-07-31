<?php

use Illuminate\Database\Seeder;
use  Illuminate\Support\Facades\DB;
use App\Produit;
use App\Produit_img;

class ProduitTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /***********************************************************************
         * Categorie : Fruits
         * ********************************************************************/

    	$objProduit=new Produit();
    	$objProduit->designation = 'Guayave';
    	$objProduit->qte = 1;
    	$objProduit->prix_produit = 25;
    	$objProduit->description = '';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 1;
    	$objProduit->user_prod_id = 11;
    	$objProduit->unite_id = 4;
    	$objProduit->volume_id = 4;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/fruits/cageot-guayave.jpg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Guayave");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}


    	$objProduit=new Produit();
    	$objProduit->designation = 'Cacao';
    	$objProduit->qte = 1;
    	$objProduit->prix_produit = 25;
    	$objProduit->description = 'Production récente, de qualité. Ce cacao est réalisé dans un sol nouvellement exploité.';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 1;
    	$objProduit->user_prod_id = 12;
    	$objProduit->unite_id = 1;
		$objProduit->volume_id = 3;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/fruits/cacao-en-sac.jpg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Cacao");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}


    	$objProduit=new Produit();
    	$objProduit->designation = 'Mangue';
    	$objProduit->qte = 2;
    	$objProduit->prix_produit = 50;
    	$objProduit->description = '';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 1;
    	$objProduit->user_prod_id = 13;
    	$objProduit->unite_id = 4;
		$objProduit->volume_id = 4;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/fruits/mangue-en-cageot.jpg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Mangue");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}


        /*****************************************************************************
         * Categorie : Legumes
         * **************************************************************************/

        $objProduit=new Produit();
    	$objProduit->designation = 'Choux verts';
    	$objProduit->qte = 5;
    	$objProduit->prix_produit = 50;
    	$objProduit->description = '';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 2;
    	$objProduit->user_prod_id = 14;
    	$objProduit->unite_id = 1;
		$objProduit->volume_id = 3;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/legumes/chou-vert-en-sac.jpg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Choux verts");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}


        $objProduit=new Produit();
    	$objProduit->designation = 'Gombos';
    	$objProduit->qte = 2;
    	$objProduit->prix_produit = 25;
    	$objProduit->description = '';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 2;
    	$objProduit->user_prod_id = 14;
    	$objProduit->unite_id = 4;
		$objProduit->volume_id = 4;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/legumes/gombos-cageot.jpg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Gombos");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}

        /*****************************************************************************
         * Categorie : Tubercules
         * **************************************************************************/
        $objProduit=new Produit();
    	$objProduit->designation = 'Manioc';
    	$objProduit->qte = 1;
    	$objProduit->prix_produit = 25;
    	$objProduit->description = '';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 3;
    	$objProduit->user_prod_id = 12;
    	$objProduit->unite_id = 1;
		$objProduit->volume_id = 1;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/tubercules/manioc-en-sac.jpeg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Manioc");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}


        $objProduit=new Produit();
    	$objProduit->designation = 'Ignam Blanc';
    	$objProduit->qte = 5;
    	$objProduit->prix_produit = 50;
    	$objProduit->description = '';
    	$objProduit->type_vente = 'vente directe';
    	$objProduit->categorie_id = 3;
    	$objProduit->user_prod_id = 15;
    	$objProduit->unite_id = 1;
		$objProduit->volume_id = 1;
    	$objProduit->published = 1;
    	$objProduit->statut_produit_id = 1;
    	$objProduit->generateReference();
    	$objProduit->generateAlias($objProduit->designation);
    	if($objProduit->save()){
    		$objImage = new Produit_img();
    		$objImage->name = 'api/drat-api/public/ressources/tubercules/igname-blanc.jpg';
    		$objImage->published = 1;
    		$objImage->generateReference();
    		$objImage->generateAlias($objImage->name);
    		$objImage->produit()->associate($objProduit);

    		if(!$objImage->save())
    		{
    			$this->command->info("Fail Seeded Image:Ignam Blanc");
    		}else{
    			$this->command->info("Seeded Image: ". $objImage->name);
    		}

    	}else{
    		DB::rollback();
    		$this->_errorCode = 1;
    		if(in_array($this->_env, ['local', 'development']))
    		{
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
    		$this->_response['error_code']  = $this->prepareErrorCode();
    		return response()->json( $this->_response );
    	}


        $objProduit=new Produit();
        $objProduit->designation = 'Tomate';
        $objProduit->qte = 10;
        $objProduit->prix_produit = 25 ;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 1;
        $objProduit->user_prod_id = 11;
        $objProduit->unite_id = 4;
        $objProduit->volume_id = 4;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/fruits/papaye-nu.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:Guayave");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'patate douce';
        $objProduit->qte = 50;
        $objProduit->prix_produit = 10 ;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 3;
        $objProduit->user_prod_id = 11;
        $objProduit->unite_id = 4;
        $objProduit->volume_id = 4;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/tubercules/patate-douce.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:patate");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'cerise';
        $objProduit->qte = 50;
        $objProduit->prix_produit = 10 ;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 1;
        $objProduit->user_prod_id = 11;
        $objProduit->unite_id = 4;
        $objProduit->volume_id = 4;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/fruits/cerises.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:cerise");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'pomme';
        $objProduit->qte = 100;
        $objProduit->prix_produit = 15 ;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 1;
        $objProduit->user_prod_id = 11;
        $objProduit->unite_id = 4;
        $objProduit->volume_id = 4;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/fruits/pomme.jpeg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:Pomme");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'kiwi';
        $objProduit->qte = 100;
        $objProduit->prix_produit = 15 ;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 1;
        $objProduit->user_prod_id = 11;
        $objProduit->unite_id = 4;
        $objProduit->volume_id = 4;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/fruits/kiwi.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:kiwi");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'Fraise';
        $objProduit->qte = 12;
        $objProduit->prix_produit = 10 ;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 1;
        $objProduit->user_prod_id = 11;
        $objProduit->unite_id = 4;
        $objProduit->volume_id = 4;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/fruits/fraise.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:fraise");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'carotte';
        $objProduit->qte = 11;
        $objProduit->prix_produit = 25;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 3;
        $objProduit->user_prod_id = 12;
        $objProduit->unite_id = 1;
        $objProduit->volume_id = 1;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/tubercules/carottes.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:carotte");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'Pommes de terre';
        $objProduit->qte = 11;
        $objProduit->prix_produit = 25;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 3;
        $objProduit->user_prod_id = 12;
        $objProduit->unite_id = 1;
        $objProduit->volume_id = 1;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/tubercules/pommes-de-terre.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:Pommes-de-terre");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'Ginseng';
        $objProduit->qte = 11;
        $objProduit->prix_produit = 25;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 3;
        $objProduit->user_prod_id = 12;
        $objProduit->unite_id = 1;
        $objProduit->volume_id = 1;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/tubercules/ginseng-dossier.png';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:ginseng-dossier");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $objProduit=new Produit();
        $objProduit->designation = 'Igname';
        $objProduit->qte = 11;
        $objProduit->prix_produit = 25;
        $objProduit->description = '';
        $objProduit->type_vente = 'vente directe';
        $objProduit->categorie_id = 3;
        $objProduit->user_prod_id = 12;
        $objProduit->unite_id = 1;
        $objProduit->volume_id = 1;
        $objProduit->published = 1;
        $objProduit->statut_produit_id = 1;
        $objProduit->generateReference();
        $objProduit->generateAlias($objProduit->designation);
        if($objProduit->save()){
            $objImage = new Produit_img();
            $objImage->name = 'api/drat-api/public/ressources/tubercules/igname.jpg';
            $objImage->published = 1;
            $objImage->generateReference();
            $objImage->generateAlias($objImage->name);
            $objImage->produit()->associate($objProduit);

            if(!$objImage->save())
            {
                $this->command->info("Fail Seeded Image:igname");
            }else{
                $this->command->info("Seeded Image: ". $objImage->name);
            }

        }else{
            DB::rollback();
            $this->_errorCode = 1;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message'] = 'Erreur d\'enregistrement';
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }
    }


}
