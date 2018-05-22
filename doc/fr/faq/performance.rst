.. _performance:

===========
Performance
===========


Optimisations InnoDB 
====================

Cette section n'est pas encore documentée.

Schema des Bases de données
===========================

Le schema de la base de données Centreon peut être consulté ici : 

.. image:: ../database/centreon.png


Le schéma de la base de données Centreon_storage ici :

.. image:: ../database/centreon-storage.png

*********
RRDCacheD
*********

RRDCacheD est un processus qui permet de limiter les E/S disque lors de la mise à jour des graphiques
de performance et/ou des graphiques de statut (fichiers RRDs).
Pour cela, le processus RRDCacheD est appelé par le module Centreon Broker et mutualise les écritures 
sur disque plutôt que d'enregistrer une à une les données issues de la collecte.

Installation
============

Le processus RRDCacheD est disponible dnas le paquet **rrdtool** déjà installé sur votre serveur Centreon.

Configuration
=============

Options générales
*****************

Éditer le fichier **/etc/sysconfig/rrdcached** et modifier les informations suivantes ::

    # Settings for rrdcached
    OPTIONS="-m 664 -l unix:/var/rrdtool/rrdcached/rrdcached.sock -s rrdcached -b 	/var/rrdtool/rrdcached -w 3600 -z 3600 -f 7200"
    RRDC_USER=rrdcached

.. note::
    L'ordre des options est très important, si l'option ** -m 664** est placée après l'option **-l unix:/var/rrdtool/rrdcached/rrdcached.sock** alors la socket sera créée avec les mauvais droits.

Concernant les autres options importantes :

+--------+-----------------------------------------------------------------------------------+
| Option | Description                                                                       |   
+========+===================================================================================+
| -w     | Les données sont écrites sur le disques toutes les x secondes (ici 3600s donc 1h) |
+--------+-----------------------------------------------------------------------------------+
| -z     | Doit être inférieur ou égale à l'option **-w**. RRDCacheD utilise une valeur      |
|        | aléatoire dans l'intervalle [0:-z] pour décaler l'écriture d'un fichier afin      |
|        | d'éviter que trop d'écritures soient mises en attente simultanément.              |
+--------+-----------------------------------------------------------------------------------+
| -f     | Correspond à un temps maximum de mise à jour (timeout). Si dans le cache des      |
|        | valeurs sont supérieures ou égales au nombre de secondes définies, alors celle-ci |
|        | sont automatiquement écrite sur le disque.                                        |
+--------+-----------------------------------------------------------------------------------+

.. note::
    Ces valeurs doivent être adaptées en fonction du besoin/des contraintes de la plate-forme concernée !

Configuration des groupes
*************************

Créer les groupes en exécutant les commandes suivantes ::

    # usermod -a -g rrdcached centreon-broker
    # usermod -a -g rrdcached apache
    # usermod -a -g centreon rrdcached
    # usermod -a -g centreon-broker rrdcached

Redémarrer le processus Apache pour prendre en compte les modifications ::

    # /etc/init.d/httpd restart

Démarrer le processus RRDCacheD ::

    # /etc/init.d/rrdcached start

Configurer le processus dans l'interface web Centreon
*****************************************************

Se rendre dans le menu **Administration -> Options -> RRDTool**, activer l'utilisation du processus et renseigner l'accès au socket **/var/rrdtool/rrdcached/rrdcached.sock** :

.. image:: /images/faq/rrdcached_config.png
    :align: center

.. warning::
    Attention, même si la modification a été réalisé dans le menu **Administration**, il est nécessaire d'exporter la configuration et de redémarrer le processus centreon-broker via un export de la configuration du serveur central et un redémarrage du processus cbd.

.. image:: /images/faq/rrd_file_generator.png
    :align: center

Interface web Centreon
======================

La mise en place de rrdcached fait que les graphiques ne sont plus mis à jours en temps réel.
Il est donc possible de voir un petit blanc sur la droite de certains graphiques. 
Cela veut dire que les données sont encore dans le cache du processus, cela est normal !

.. warning::
    Attention, si le **processus crash** pour une raison quelconque (aucune en théorie c'est plutôt stable), les **données** sont **perdues**, donc aucun moyen de les rejouer sauf en reconstruisant les graphiques via centreon-broker.
