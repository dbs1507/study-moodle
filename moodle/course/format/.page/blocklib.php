<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * @package format_page
 * @category format
 * @author valery fremaux (valery.fremaux@gmail.com)
 * @copyright 2008 Valery Fremaux (Edunao.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/course/format/page/page.class.php');

/**
 * This script proposes an enhanced version of the class block_manager
 * that allows sufficant overrides to handle blocks in multipages formats
 * using format_page_items as additional information to build regions.
 *
 */
class page_enabled_block_manager extends block_manager {

    /**
     * Add a block to the current page, or related pages. The block is added to
     * context $this->page->contextid. If $pagetypepattern $subpagepattern
     * The bloc is recorded also in format_page_items
     *
     * @param string $blockname The type of block to add.
     * @param string $region the block region on this page to add the block to.
     * @param integer $weight determines the order where this block appears in the region.
     * @param boolean $showinsubcontexts whether this block appears in subcontexts, or just the current context.
     * @param string|null $pagetypepattern which page types this block should appear on. Defaults to just the current page type.
     * @param string|null $subpagepattern which subpage this block should appear on. NULL = any (the default), otherwise only the specified subpage.
     */
    public function add_block($blockname, $region, $weight, $showinsubcontexts, $pagetypepattern = null, $subpagepattern = null) {
        global $DB;

        /*
         * Allow invisible blocks because this is used when adding default page blocks, which
         * might include invisible ones if the user makes some default blocks invisible
         */
        $this->check_known_block_type($blockname, true);
        $this->check_region_is_known($region);
        $this->check_page_format_conditions($subpagepattern);

        if (empty($pagetypepattern)) {
            $pagetypepattern = $this->page->pagetype;
        }

        $blockinstance = new stdClass;
        $blockinstance->blockname = $blockname;
        $blockinstance->parentcontextid = $this->page->context->id;
        $blockinstance->showinsubcontexts = !empty($showinsubcontexts);
        $blockinstance->pagetypepattern = $pagetypepattern;
        $blockinstance->subpagepattern = $subpagepattern;
        $blockinstance->defaultregion = $region;
        $blockinstance->defaultweight = $weight;
        $blockinstance->configdata = '';
        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

        // Ensure the block context is created.
        context_block::instance($blockinstance->id);

        // If the new instance was created, allow it to do additional setup.
        if ($block = block_instance($blockname, $blockinstance)) {
            $block->instance_create();
        }

        // Inserts into format_page_items on curent page.
        if ($this->page->course->format == 'page') {
            /*
            // This is a silly collision case with module "page".
            if (is_array(@$_POST['page'])) {
                $page = course_page::get_current_page($this->page->course->id);
                $pageid = $page->id;
            } else {
                if (!$pageid = optional_param('page', 0, PARAM_INT)) {
                    if (!$pageid = @$COURSE->pageid) {
                        $page = course_page::get_current_page($this->page->course->id);
                        $pageid = $page->id;
                    }
                }
            }
            */

            // In this case, $subpagepattern is mandatory and holds the pageid
            $pageid = str_replace('page-', '', $subpagepattern);

            $pageitem = new StdClass();
            $pageitem->pageid = $pageid;
            $pageitem->blockinstance = $blockinstance->id;
            $pageitem->visible = 1; // This is not used any more.
            $pageitem->sortorder = 1; // This is not used any more.
            $DB->insert_record('format_page_items', $pageitem);
        }

        // We need this for extra processing after block creation.
        return $blockinstance;
    }

    /**
     * Knows how to turn around the theme cascade.
     */
    public function add_block_at_end_of_page_region($blockname, $pageid = 0) {
        global $COURSE, $CFG;

        if ($COURSE->format != 'page') {
            throw new coding_exception('This block add variant should not be used in non page format');
        }

        $this->page->initialise_theme_and_output();

        // Forces region existance anyway in page format, whatever page we are working in.
        $this->regions['side-post'] = 1;
        $this->regions['main'] = 1;
        $this->regions['side-pre'] = 1;

        if (!empty($CFG->format_page_default_region)) {
            $defaulregion = $CFG->format_page_default_region;
        } else {
            $defaulregion = 'main';
        }

        // We need recalculate weight for region by our own.
        $weight = $this->compute_weight_in_page($defaulregion, $pageid);

        // Special case.
        // We force course view as the actual page context is a in-module context.
        $pagetypepattern = 'course-view-*';

        return $this->add_block($blockname, $defaulregion, $weight, false, $pagetypepattern, 'page-'.$pageid);
    }

    /**
     * Creates a complete default course module instance of the given activity class and wrap it yo
     * a new page_module block intance.
     */
    public function add_course_module($modname, $region, $weight, $showinsubcontexts, $pagetypepattern = null, $subpagepattern = null) {
        global $DB;

        // @TODO : Create the activity instance
        // Not so simple to create default versions

        $pagemoduleblock = $this->add_block('page_module', $region, $weight, $showinsubcontexts, $pagetypepattern, $subpagepattern);

        // Post setup the config data to bind to the course module
        $config->cmid = $cm->id;
        $pagemoduleblock->configdata = base64_encode(serialize($config));
    }

    /**
     *
     */
    protected function check_page_format_conditions($subpagepattern) {
        global $DB;

        if ($this->page->course->format == 'page') {
            if (!preg_match('/^page-(\d+)$/', $subpagepattern, $matches)) {
                throw new coding_exception('Malformed subpage pattern in a page format course: ' . $subpagepattern);
            }

            if (!$page = $DB->get_record('format_page', array('id' => $matches[1]))) {
                throw new coding_exception('Missing page: ' . $matches[1]);
            }
    
            if ($page->courseid != $this->page->course->id) {
                throw new coding_exception('Page instance '.$page->id.':'.$page->courseid.' and course '.$this->page->course->id.' don\'t match');
            }
        }

        return true;
    }

    /**
     * Computes the blocks weight
     * @param string $defaultregion
     * @param int $pageid
     */
    public function compute_weight_in_page($defaultregion, $pageid) {
        global $DB;

        // Positionned.
        $posweight = 0 + $DB->get_field('block_positions', 'MAX(weight)', array('subpage' => 'page-'.$pageid, 'region' => $defaultregion));

        $weight = 0 + $DB->get_field('block_instances', 'MAX(defaultweight)', array('subpagepattern' => 'page-'.$pageid));

        $weight = (max($posweight, $weight));

        return 0 + $weight + 1;
    }

    /**
     * This method actually loads the blocks for our page from the database.
     * Loading blocs needs to complete standard queries with page_items mapping.
     *
     * @param boolean|null $includeinvisible
     *      null (default) - load hidden blocks if $this->page->user_is_editing();
     *      true - load hidden blocks.
     *      false - don't load hidden blocks.
     */
    public function load_blocks($includeinvisible = null) {
        global $DB, $CFG, $COURSE, $PAGE;

        if (!is_null($this->birecordsbyregion)) {
            // Already done.
            return;
        }

        if ($CFG->version < 2009050619) {
            // Upgrade/install not complete. Don't try too show any blocks.
            $this->birecordsbyregion = array();
            return;
        }

        // Ensure we have been initialised.
        if (is_null($this->defaultregion)) {
            $this->page->initialise_theme_and_output();
            // If there are still no block regions, then there are no blocks on this page.
            if (empty($this->regions)) {
                $this->birecordsbyregion = array();
                return;
            }
        }

        // Check if we need to load normal blocks.
        if ($this->fakeblocksonly) {
            $this->birecordsbyregion = $this->prepare_per_region_arrays();
            return;
        }

        if (is_null($includeinvisible)) {
            $includeinvisible = $this->page->user_is_editing();
        }
        if ($includeinvisible) {
            $visiblecheck = '';
        } else {
            $visiblecheck = '(bp.visible = 1 OR bp.visible IS NULL) AND';
        }

        $context = $this->page->context;
        $contexttest = 'bi.parentcontextid = :contextid2';
        $parentcontextparams = array();
        $parentcontextids = $context->get_parent_context_ids(); // > M2.6
        if ($parentcontextids && ($COURSE->format != 'page' || $PAGE->pagelayout == 'format_page')) {
            list($parentcontexttest, $parentcontextparams) = $DB->get_in_or_equal($parentcontextids, SQL_PARAMS_NAMED, 'parentcontext');
            $contexttest = "($contexttest OR (bi.showinsubcontexts = 1 AND bi.parentcontextid $parentcontexttest)) AND";
        } else {
            $contexttest .= ' AND';
        }

        $pagetypepatterns = matching_page_type_patterns($this->page->pagetype);
        list($pagetypepatterntest, $pagetypepatternparams) =
                $DB->get_in_or_equal($pagetypepatterns, SQL_PARAMS_NAMED, 'pagetypepatterntest');

        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = bi.id AND ctx.contextlevel = :contextlevel)";

        // Computes an extra page related clause.
        $pageclause = '';
        $pagejoin = '';
        if ($COURSE->format == 'page') {
            if ($PAGE->pagelayout == 'format_page') {
                // Special weird case : for module "page" : page is an array, but is only present on non page format pagetypes...
                if (is_array(@$_POST['page'])) {
                    $page = course_page::get_current_page($COURSE->id);
                    $pageclause = " fpi.pageid = $page->id AND ";
                    $this->page->set_subpage('page-'.$page->id);
                } else {
                    if ($pageid = optional_param('page', 0, PARAM_INT)) {
                        $pageclause = " fpi.pageid = $pageid AND ";
                        $this->page->set_subpage('page-'.$pageid);
                    } else {
                        if ($page = course_page::get_current_page($COURSE->id)) {
                            $pageclause = " fpi.pageid = $page->id AND ";
                            $this->page->set_subpage('page-'.$page->id);
                        } else {
                            // No pages standard for no blocks !!
                            $pageclause = " fpi.pageid = 0 AND ";
                        }
                    }
                }
                $pagejoin = "JOIN {format_page_items} fpi ON bi.id = fpi.blockinstance ";
            } else {
                // For other non paged layouts used in context of format page, get only navigation and settings.
                $pagejoin = "LEFT JOIN {format_page_items} fpi ON bi.id = fpi.blockinstance ";
                $pageclause = " (bi.showinsubcontexts = 1 OR bi.blockname IN ('navigation', 'settings')) AND ";
            }
        }

        $params = array(
            'contextlevel' => CONTEXT_BLOCK,
            'subpage1' => $this->page->subpage,
            'subpage2' => $this->page->subpage,
            'contextid1' => $context->id,
            'contextid2' => $context->id,
            'pagetype' => $this->page->pagetype,
        );
        if ($this->page->subpage === '') {
            $params['subpage1'] = '';
            $params['subpage2'] = '';
        }
        $sql = "SELECT DISTINCT
                    bi.id,
                    bp.id AS blockpositionid,
                    bi.blockname,
                    bi.parentcontextid,
                    bi.showinsubcontexts,
                    bi.pagetypepattern,
                    bi.subpagepattern,
                    bi.defaultregion,
                    bi.defaultweight,
                    COALESCE(bp.visible, 1) AS visible,
                    COALESCE(bp.region, bi.defaultregion) AS region,
                    COALESCE(bp.weight, bi.defaultweight) AS weight,
                    bi.configdata
                    $ccselect
                FROM
                    {block_instances} bi
                $pagejoin
                JOIN
                    {block} b
                ON
                    bi.blockname = b.name
                LEFT JOIN
                    {block_positions} bp
                ON
                    bp.blockinstanceid = bi.id AND
                    bp.contextid = :contextid1 AND
                    bp.pagetype = :pagetype AND
                    bp.subpage = :subpage1
                    $ccjoin
                WHERE
                    $pageclause
                    $contexttest
                    bi.pagetypepattern $pagetypepatterntest AND
                    (bi.subpagepattern IS NULL OR bi.subpagepattern = :subpage2) AND
                    $visiblecheck
                    b.visible = 1
                ORDER BY
                    COALESCE(bp.region, bi.defaultregion),
                    COALESCE(bp.weight, bi.defaultweight),
                    bi.id";
        $blockinstances = $DB->get_records_sql($sql, $params + $parentcontextparams + $pagetypepatternparams);

        $this->birecordsbyregion = $this->prepare_per_region_arrays();

        $unknown = array();
        $inpage = array();

        foreach ($blockinstances as $bi) {
            context_helper::preload_from_record($bi);
            if (!$instance = block_instance($bi->blockname)) {
                continue;
            }
            if ($instance->instance_allow_multiple() || !array_key_exists($bi->blockname, $inpage)) {
                $inpage[$bi->blockname] = 1;
                if ($this->is_known_region($bi->region)) {
                    $this->birecordsbyregion[$bi->region][] = $bi;
                } else {
                    $unknown[] = $bi;
                }
            }
        }

        // We are NOT editing a block.
        if (!isset($_GET['bui_editid'])) {

            /*
             * Pages don't necessarily have a defaultregion. The  one time this can
             * happen is when there are no theme block regions, but the script itself
             * has a block region in the main content area.
             */
            if (!empty($this->defaultregion)) {
                $this->birecordsbyregion[$this->defaultregion] = array_merge($this->birecordsbyregion[$this->defaultregion], $unknown);
            }
        }
    }

    /**
     * Handle deleting a block.
     * @return boolean true if anything was done. False if not.
     */
    public function process_url_delete() {
        global $COURSE, $DB;

        $blockid = optional_param('bui_deleteid', null, PARAM_INTEGER);
        if (!$blockid) {
            return false;
        }

        require_sesskey();

        $block = $this->page->blocks->find_instance($blockid);

        if (!$block->user_can_edit() || !$this->page->user_can_edit_blocks() || !$block->user_can_addto($this->page)) {
            throw new moodle_exception('nopermissions', '', $this->page->url->out(), get_string('deleteablock'));
        }

        blocks_delete_instance($block->instance);

        if ($COURSE->format == 'page') {
            if (!$pageid = optional_param('page', 0, PARAM_INT)) {
                $page = course_page::get_current_page($COURSE->id);
                $pageid = $page->id;
            }
            $DB->delete_records('format_page_items', array('pageid' => $pageid, 'blockinstance' => $blockid));
        }

        // If the page URL was a guess, it will contain the bui_... param, so we must make sure it is not there.
        $this->page->ensure_param_not_in_url('bui_deleteid');

        return true;
    }

    /**
     * Ensure that there is some content within the given region
     * This override avoids printing the add_block_ui in columns
     * as already provided by course top editing window
     *
     * @param string $region The name of the region to check
     */
    public function ensure_content_created($region, $output) {
        global $COURSE;

        $this->ensure_instances_exist($region);
        if (!array_key_exists($region, $this->visibleblockcontent)) {
            $contents = array();
            if (array_key_exists($region, $this->extracontent)) {
                $contents = $this->extracontent[$region];
            }
            $contents = array_merge($contents, $this->create_block_contents($this->blockinstances[$region], $output, $region));
            if ($COURSE->format != 'page') {
                if ($region == $this->defaultregion) {
                    $addblockui = block_add_block_ui($this->page, $output);
                    if ($addblockui) {
                        $contents[] = $addblockui;
                    }
                }
            }
            $this->visibleblockcontent[$region] = $contents;
        }
    }

    /**
     * This reworked version do know how to redirect editing link in special case of a 
     * page_module block linked to a course module. Page module instance is NOT editable
     * in direct;
     *
     * Get the appropriate list of editing icons for a block. This is used
     * to set {@link block_contents::$controls} in {@link block_base::get_contents_for_output()}.
     *
     * @param $output The core_renderer to use when generating the output. (Need to get icon paths.)
     * @return an array in the format for {@link block_contents::$controls}
     */
    public function edit_controls($block) {
        global $CFG;

        $controls = array();
        $actionurl = $this->page->url->out(false, array('sesskey' => sesskey()));
        $blocktitle = format_string($block->title);
        if (empty($blocktitle)) {
            $blocktitle = $block->arialabel;
        }

        if ($this->page->user_can_edit_blocks()) {
            // Move icon.
            $str = new lang_string('moveblock', 'block', $blocktitle);
            $controls[] = new action_menu_link_primary(
                new moodle_url($actionurl, array('bui_moveid' => $block->instance->id)),
                new pix_icon('t/move', $str, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str,
                array('class' => 'editing_move')
            );
        }

        if ($this->page->user_can_edit_blocks() || $block->user_can_edit()) {
            // Edit config icon - always show - needed for positioning UI.
            // CHANGE for page format
            $str = new lang_string('configureblock', 'block', $blocktitle);
            $controls[] = new action_menu_link_secondary(
                new moodle_url($actionurl, array('bui_editid' => $block->instance->id)),
                new pix_icon('t/edit', $str, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str,
                array('class' => 'editing_edit')
            );
            // /CHANGE
        }

        if ($this->page->user_can_edit_blocks() && $block->instance_can_be_hidden()) {
            // Show/hide icon.
            if ($block->instance->visible) {
                $str = new lang_string('hideblock', 'block', $blocktitle);
                $url = new moodle_url($actionurl, array('bui_hideid' => $block->instance->id));
                $icon = new pix_icon('t/hide', $str, 'moodle', array('class' => 'iconsmall', 'title' => ''));
                $attributes = array('class' => 'editing_hide');
            } else {
                $str = new lang_string('showblock', 'block', $blocktitle);
                $url = new moodle_url($actionurl, array('bui_showid' => $block->instance->id));
                $icon = new pix_icon('t/show', $str, 'moodle', array('class' => 'iconsmall', 'title' => ''));
                $attributes = array('class' => 'editing_show');
            }
            $controls[] = new action_menu_link_secondary($url, $icon, $str, $attributes);
        }

        // Assign roles icon.
        if (has_capability('moodle/role:assign', $block->context)) {
            /*
             * TODO: please note it is sloppy to pass urls through page parameters!!
             *      it is shortened because some web servers (e.g. IIS by default) give
             *      a 'security' error if you try to pass a full URL as a GET parameter in another URL.
             */
            $return = $this->page->url->out(false);
            $return = str_replace($CFG->wwwroot . '/', '', $return);

            $str = new lang_string('assignrolesinblock', 'block', $blocktitle);
            $rolesurl = new moodle_url('/admin/roles/assign.php', array('contextid' => $block->context->id, 'returnurl' => urlencode($return)));
            $controls[] = new action_menu_link_secondary(
                $rolesurl,
                new pix_icon('t/assignroles', $str, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str,
                array('class' => 'editing_roles')
            );
        }

        if ($this->user_can_delete_block($block)) {
            // Delete icon.
            $str = new lang_string('deleteblock', 'block', $blocktitle);
            $controls[] = new action_menu_link_secondary(
                new moodle_url($actionurl, array('bui_deleteid' => $block->instance->id)),
                new pix_icon('t/delete', $str, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str,
                array('class' => 'editing_delete')
            );
        }

        return $controls;
    }

    /**
     * The list of block types that may be added to this page.
     *
     * @return array block name => record from block table.
     */
    public function get_addable_blocks() {
        global $CFG;

        $this->check_is_loaded();

        if (!is_null($this->addableblocks)) {
            return $this->addableblocks;
        }

        // Lazy load.
        $this->addableblocks = array();

        $allblocks = blocks_get_record();
        if (empty($allblocks)) {
            return $this->addableblocks;
        }

        $unaddableblocks = self::get_undeletable_block_types();
        $pageformat = $this->page->pagetype;
        foreach ($allblocks as $block) {
            if (!$bi = block_instance($block->name)) {
                continue;
            }
            if ($block->name == 'page_module') {
                // Page_module is a technical block not for user's explicit use.
                continue;
            }

            // NEW : Add user equipment check
            if (is_dir($CFG->dirroot.'/local/userequipment')) {
                $config = get_config('local_userequipment');
                if (!empty($config->enabled)) {
                    include_once($CFG->dirroot.'/local/userequipment/lib.php');
                    if (!check_user_equipment('block', $block->name, $USER->id)) {
                        continue;
                    }
                }
            }

            if ($block->visible /* && !in_array($block->name, $unaddableblocks) */ &&
                    ($bi->instance_allow_multiple() || !$this->is_block_present($block->name)) &&
                    blocks_name_allowed_in_format($block->name, $pageformat) &&
                    $bi->user_can_addto($this->page)) {
                $this->addableblocks[$block->name] = $block;
            }
        }

        return $this->addableblocks;
    }

    /**
     * Return a {@link block_contents} representing the add a new block UI, if
     * this user is allowed to see it.
     *
     * @return block_contents an appropriate block_contents, or null if the user
     * cannot add any blocks here.
     * 
     * Possibly Deprecated @see lib.php format_page_block_add_block_ui
     */
    function block_add_block_ui($page, $output) {
        global $CFG, $OUTPUT, $DB;

        if (!$page->user_is_editing() || !$page->user_can_edit_blocks()) {
            return null;
        }

        $bc = new block_contents();
        $bc->title = get_string('addblock');
        $bc->add_class('block_adminblock');
        $bc->attributes['data-block'] = 'adminblock';

        $missingblocks = $page->blocks->get_addable_blocks();
        if (empty($missingblocks)) {
            $bc->content = get_string('noblockstoaddhere');
            return $bc;
        }

        $menu = array();
        foreach ($missingblocks as $block) {
            // CHANGE
            $familyname = $DB->get_field('format_page_plugins', 'familyname', array('type' => 'block', 'plugin' => $block->name));
            if ($familyname) {
                $family = format_string($DB->get_field('format_page_pfamily', 'name', array('shortname' => $familyname)));
            } else {
                $family = get_string('otherblocks', 'format_page');
            }
            // /CHANGE
            $blockobject = block_instance($block->name);
            if ($blockobject !== false && $blockobject->user_can_addto($page)) {
                $menu[$family][$block->name] = $blockobject->get_title();
            }
        }
        $i = 0;
        foreach ($menu as $f => $m) {
            $selectmenu[$i][$f] = $m;
            $i++;
        }
        // core_collator::asort($menu);

        $actionurl = new moodle_url($page->url, array('sesskey' => sesskey()));
        $select = new single_select($actionurl, 'bui_addblock', $selectmenu, null, array('' => get_string('adddots')), 'add_block');
        $select->set_label(get_string('addblock'), array('class' => 'accesshide'));
        $bc->content = $OUTPUT->render($select);
        return $bc;
    }

    /**
     *
     * For debug purpose.
     */
    public function print_raw_blocks($level = 2) {
        print_object_nr($this->birecordsbyregion, $level);
    }
}