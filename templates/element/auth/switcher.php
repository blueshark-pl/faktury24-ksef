<?php
/**
 * Element: Auth Switcher Offcanvas
 * Plik: templates/element/auth/switcher.php
 */
?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="switcher-canvas" aria-labelledby="offcanvasRightLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title text-default" id="offcanvasRightLabel">Switcher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="border-bottom border-block-end-dashed">
            <div class="nav nav-tabs nav-justified" id="switcher-main-tab" role="tablist">
                <button class="nav-link active" id="switcher-home-tab" data-bs-toggle="tab" data-bs-target="#switcher-home"
                    type="button" role="tab" aria-controls="switcher-home" aria-selected="true">Theme Styles</button>
                <button class="nav-link" id="switcher-profile-tab" data-bs-toggle="tab" data-bs-target="#switcher-profile"
                    type="button" role="tab" aria-controls="switcher-profile" aria-selected="false">Theme Colors</button>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active border-0" id="switcher-home" role="tabpanel" aria-labelledby="switcher-home-tab" tabindex="0">
                <div>
                    <p class="switcher-style-head">Theme Color Mode:</p>
                    <div class="row switcher-style g-0">
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-light-theme">Light</label>
                                <input class="form-check-input" type="radio" name="theme-style" id="switcher-light-theme" checked>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-dark-theme">Dark</label>
                                <input class="form-check-input" type="radio" name="theme-style" id="switcher-dark-theme">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="switcher-style-head">Directions:</p>
                    <div class="row switcher-style g-0">
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-ltr">LTR</label>
                                <input class="form-check-input" type="radio" name="direction" id="switcher-ltr" checked>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-rtl">RTL</label>
                                <input class="form-check-input" type="radio" name="direction" id="switcher-rtl">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="switcher-style-head">Navigation Styles:</p>
                    <div class="row switcher-style g-0">
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-vertical">Vertical</label>
                                <input class="form-check-input" type="radio" name="navigation-style" id="switcher-vertical" checked>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-horizontal">Horizontal</label>
                                <input class="form-check-input" type="radio" name="navigation-style" id="switcher-horizontal">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="navigation-menu-styles">
                    <p class="switcher-style-head">Navigation Menu Style:</p>
                    <div class="row switcher-style pb-2">
                        <?php
                        $menuIds = [
                            ['switcher-menu-click','Menu Click'],
                            ['switcher-menu-hover','Menu Hover'],
                            ['switcher-icon-click','Icon Click'],
                            ['switcher-icon-hover','Icon Hover'],
                        ];
                        foreach ($menuIds as [$id,$label]): ?>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="<?= $id ?>"><?= h($label) ?></label>
                                <input class="form-check-input" type="radio" name="navigation-menu-styles" id="<?= $id ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-icon-overlay">Icon Overlay</label>
                                <input class="form-check-input" type="radio" name="navigation-menu-styles" id="switcher-icon-overlay">
                            </div>
                        </div>
                    </div>
                    <div class="px-4 pb-3 text-secondary fs-11">
                        <span class="fw-medium fs-12 text-dark me-2 d-inline-block">Note:</span>Works same for both Vertical and Horizontal
                    </div>
                </div>

                <div>
                    <p class="switcher-style-head">Page Styles:</p>
                    <div class="row switcher-style g-0">
                        <?php
                        $pageStyles = [
                            ['switcher-regular','Regular', true],
                            ['switcher-classic','Classic', false],
                            ['switcher-modern','Modern', false],
                        ];
                        foreach ($pageStyles as [$id,$label,$checked]): ?>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="<?= $id ?>"><?= h($label) ?></label>
                                <input class="form-check-input" type="radio" name="page-styles" id="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <p class="switcher-style-head">Layout Width Styles:</p>
                    <div class="row switcher-style g-0">
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-full-width">Full Width</label>
                                <input class="form-check-input" type="radio" name="layout-width" id="switcher-full-width" checked>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-boxed">Boxed</label>
                                <input class="form-check-input" type="radio" name="layout-width" id="switcher-boxed">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="switcher-style-head">Menu Positions:</p>
                    <div class="row switcher-style g-0">
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-menu-fixed">Fixed</label>
                                <input class="form-check-input" type="radio" name="menu-positions" id="switcher-menu-fixed" checked>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-menu-scroll">Scrollable</label>
                                <input class="form-check-input" type="radio" name="menu-positions" id="switcher-menu-scroll">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="switcher-style-head">Header Positions:</p>
                    <div class="row switcher-style g-0">
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-header-fixed">Fixed</label>
                                <input class="form-check-input" type="radio" name="header-positions" id="switcher-header-fixed" checked>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="switcher-header-scroll">Scrollable</label>
                                <input class="form-check-input" type="radio" name="header-positions" id="switcher-header-scroll">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sidemenu-layout-styles">
                    <p class="switcher-style-head">Sidemenu Layout Syles:</p>
                    <div class="row switcher-style pb-2">
                        <?php
                        $sideMenu = [
                            ['switcher-default-menu','Default Menu', true],
                            ['switcher-closed-menu','Closed Menu', false],
                            ['switcher-icontext-menu','Icon Text', false],
                            ['switcher-icon-overlay','Icon Overlay', false],
                            ['switcher-detached','Detached', false],
                            ['switcher-double-menu','Double Menu', false],
                        ];
                        foreach ($sideMenu as [$id,$label,$checked]): ?>
                        <div class="col-sm-6">
                            <div class="form-check switch-select">
                                <label class="form-check-label" for="<?= $id ?>"><?= h($label) ?></label>
                                <input class="form-check-input" type="radio" name="sidemenu-layout-styles" id="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="px-4 pb-3 text-secondary fs-11"><span class="fw-medium fs-12 text-dark me-2 d-inline-block">Note:</span>Navigation menu styles won't work here.</div>
                </div>
            </div>

            <div class="tab-pane fade border-0" id="switcher-profile" role="tabpanel" aria-labelledby="switcher-profile-tab" tabindex="0">
                <div>
                    <div class="theme-colors">
                        <p class="switcher-style-head">Menu Colors:</p>
                        <div class="d-flex switcher-style pb-2">
                            <?php
                            $menuColors = [
                                ['switcher-menu-light','Light Menu','color-white', true],
                                ['switcher-menu-dark','Dark Menu','color-dark', false],
                                ['switcher-menu-primary','Color Menu','color-primary', false],
                                ['switcher-menu-gradient','Gradient Menu','color-gradient', false],
                                ['switcher-menu-transparent','Transparent Menu','color-transparent', false],
                            ];
                            foreach ($menuColors as [$id,$title,$cls,$checked]): ?>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input <?= $cls ?>"
                                       data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($title) ?>"
                                       type="radio" name="menu-colors" id="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="px-4 pb-3 text-muted fs-11">Note:If you want to change color Menu dynamically change from below Theme Primary color picker</div>
                    </div>

                    <div class="theme-colors">
                        <p class="switcher-style-head">Header Colors:</p>
                        <div class="d-flex switcher-style pb-2">
                            <?php
                            $headerColors = [
                                ['switcher-header-light','Light Header','color-white', true],
                                ['switcher-header-dark','Dark Header','color-dark', false],
                                ['switcher-header-primary','Color Header','color-primary', false],
                                ['switcher-header-gradient','Gradient Header','color-gradient', false],
                                ['switcher-header-transparent','Transparent Header','color-transparent', false],
                            ];
                            foreach ($headerColors as [$id,$title,$cls,$checked]): ?>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input <?= $cls ?>"
                                       data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($title) ?>"
                                       type="radio" name="header-colors" id="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="px-4 pb-3 text-muted fs-11">Note:If you want to change color Header dynamically change from below Theme Primary color picker</div>
                    </div>

                    <div class="theme-colors">
                        <p class="switcher-style-head">Theme Primary:</p>
                        <div class="d-flex flex-wrap align-items-center switcher-style">
                            <?php
                            $primaries = [
                                'switcher-primary','switcher-primary1','switcher-primary2','switcher-primary3','switcher-primary4'
                            ];
                            foreach ($primaries as $id): ?>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input color-<?= h(str_replace('switcher-','',$id)) ?>" type="radio" name="theme-primary" id="<?= $id ?>">
                            </div>
                            <?php endforeach; ?>
                            <div class="form-check switch-select ps-0 mt-1 color-primary-light">
                                <div class="theme-container-primary"></div>
                                <div class="pickr-container-primary"></div>
                            </div>
                        </div>
                    </div>

                    <div class="theme-colors">
                        <p class="switcher-style-head">Theme Background:</p>
                        <div class="d-flex flex-wrap align-items-center switcher-style">
                            <?php
                            $backgrounds = [
                                ['switcher-background','color-bg-1', true],
                                ['switcher-background1','color-bg-2', false],
                                ['switcher-background2','color-bg-3', false],
                                ['switcher-background3','color-bg-4', false],
                                ['switcher-background4','color-bg-5', false],
                            ];
                            foreach ($backgrounds as [$id,$cls,$checked]): ?>
                            <div class="form-check switch-select me-3">
                                <input class="form-check-input color-input <?= $cls ?>" type="radio" name="theme-background" id="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
                            </div>
                            <?php endforeach; ?>
                            <div class="form-check switch-select ps-0 mt-1 tooltip-static-demo color-bg-transparent">
                                <div class="theme-container-background"></div>
                                <div class="pickr-container-background"></div>
                            </div>
                        </div>
                    </div>

                    <div class="menu-image mb-3">
                        <p class="switcher-style-head">Menu With Background Image:</p>
                        <div class="d-flex flex-wrap align-items-center switcher-style">
                            <?php for ($i=0; $i<5; $i++): ?>
                                <div class="form-check switch-select m-2">
                                    <input class="form-check-input bgimage-input bg-img<?= $i+1 ?>" type="radio" name="theme-background" id="switcher-bg-img<?= $i ? $i : '' ?>"<?= $i===0 ? ' checked' : '' ?>>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between canvas-footer">
                <a href="javascript:void(0);" class="btn btn-primary">Buy Now</a>
                <a href="https://themeforest.net/user/wcsrm" class="btn btn-secondary">Our Portfolio</a>
                <a href="javascript:void(0);" id="reset-all" class="btn btn-danger">Reset</a>
            </div>
        </div>
    </div>
</div>
