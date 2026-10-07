<div {{ $attributes->merge(['class' => 'document-movement document-movement--mock']) }}>
    <section class="document-movement-current" aria-label="Mock current document location">
        <div>
            <span>Current Location</span>
            <strong>BAC Secretariat</strong>
        </div>
        <div>
            <span>Current Stage</span>
            <strong>Under APP Consolidation</strong>
        </div>
        <div>
            <span>Current Handler</span>
            <strong>BACSEC-004 Demo User</strong>
        </div>
        <div>
            <span>Last Updated</span>
            <strong>Oct 05, 2026 03:58 PM</strong>
        </div>
    </section>

    <section class="document-movement-tree-panel" aria-label="Mock hierarchical document movement">
        <ul class="document-movement-tree">
            <li class="movement-tree-node movement-tree-node--root is-current">
                <details open>
                    <summary class="movement-tree-row">
                        <span class="movement-tree-toggle" aria-hidden="true"></span>
                        <span class="movement-tree-state" aria-hidden="true"></span>
                        <span class="movement-tree-icon movement-tree-icon--document" aria-hidden="true"></span>
                        <span class="movement-tree-text">
                            <strong>PPMP-2026-MOCK-0001</strong>
                            <small>PPMP &middot; Mock hierarchy only</small>
                        </span>
                        <span class="movement-tree-badge">Mock</span>
                    </summary>

                    <ul>
                        <li class="movement-tree-node movement-tree-node--office is-completed">
                            <details open>
                                <summary class="movement-tree-row">
                                    <span class="movement-tree-toggle" aria-hidden="true"></span>
                                    <span class="movement-tree-state" aria-hidden="true"></span>
                                    <span class="movement-tree-icon movement-tree-icon--office" aria-hidden="true"></span>
                                    <span class="movement-tree-text">
                                        <strong>Municipal Civil Registrar</strong>
                                        <small>3 recorded movements</small>
                                    </span>
                                    <span class="movement-tree-badge">Done</span>
                                </summary>

                                <ul>
                                    <li class="movement-tree-node movement-tree-node--action is-completed">
                                        <div class="movement-tree-row movement-tree-row--leaf">
                                            <span class="movement-tree-spacer" aria-hidden="true"></span>
                                            <span class="movement-tree-state" aria-hidden="true"></span>
                                            <span class="movement-tree-icon movement-tree-icon--action" aria-hidden="true"></span>
                                            <span class="movement-tree-text">
                                                <strong>PPMP Draft Created</strong>
                                                <small>Sep 30, 2026 11:19 AM &middot; Meagan Mattutes</small>
                                                <em>Municipal Civil Registrar to Municipal Civil Registrar</em>
                                                <em>New to PPMP Draft</em>
                                            </span>
                                            <span class="movement-tree-badge">Done</span>
                                        </div>
                                    </li>

                                    <li class="movement-tree-node movement-tree-node--action is-completed">
                                        <div class="movement-tree-row movement-tree-row--leaf">
                                            <span class="movement-tree-spacer" aria-hidden="true"></span>
                                            <span class="movement-tree-state" aria-hidden="true"></span>
                                            <span class="movement-tree-icon movement-tree-icon--action" aria-hidden="true"></span>
                                            <span class="movement-tree-text">
                                                <strong>PPMP Signed</strong>
                                                <small>Sep 30, 2026 08:27 PM &middot; Meagan Mattutes</small>
                                                <em>Municipal Civil Registrar to Municipal Civil Registrar</em>
                                                <em>Pending Signatories to Signatories Completed</em>
                                            </span>
                                            <span class="movement-tree-badge">Done</span>
                                        </div>
                                    </li>

                                    <li class="movement-tree-node movement-tree-node--action is-completed">
                                        <div class="movement-tree-row movement-tree-row--leaf">
                                            <span class="movement-tree-spacer" aria-hidden="true"></span>
                                            <span class="movement-tree-state" aria-hidden="true"></span>
                                            <span class="movement-tree-icon movement-tree-icon--action" aria-hidden="true"></span>
                                            <span class="movement-tree-text">
                                                <strong>PPMP Submitted to BAC</strong>
                                                <small>Oct 01, 2026 03:22 AM &middot; Meagan Mattutes</small>
                                                <em>Municipal Civil Registrar to BAC Secretariat</em>
                                                <em>Signatories Completed to Submitted to BAC</em>
                                            </span>
                                            <span class="movement-tree-badge">Done</span>
                                        </div>
                                    </li>
                                </ul>
                            </details>
                        </li>

                        <li class="movement-tree-node movement-tree-node--office is-current">
                            <details open>
                                <summary class="movement-tree-row">
                                    <span class="movement-tree-toggle" aria-hidden="true"></span>
                                    <span class="movement-tree-state" aria-hidden="true"></span>
                                    <span class="movement-tree-icon movement-tree-icon--office" aria-hidden="true"></span>
                                    <span class="movement-tree-text">
                                        <strong>BAC Secretariat</strong>
                                        <small>2 recorded movements</small>
                                    </span>
                                    <span class="movement-tree-badge">Current</span>
                                </summary>

                                <ul>
                                    <li class="movement-tree-node movement-tree-node--action is-completed">
                                        <div class="movement-tree-row movement-tree-row--leaf">
                                            <span class="movement-tree-spacer" aria-hidden="true"></span>
                                            <span class="movement-tree-state" aria-hidden="true"></span>
                                            <span class="movement-tree-icon movement-tree-icon--action" aria-hidden="true"></span>
                                            <span class="movement-tree-text">
                                                <strong>PPMP APP Consolidation Started</strong>
                                                <small>Oct 01, 2026 04:22 AM &middot; BACSEC-004 Demo User</small>
                                                <em>BAC Secretariat to BAC Secretariat</em>
                                                <em>Submitted to BAC to Under APP Consolidation</em>
                                            </span>
                                            <span class="movement-tree-badge">Done</span>
                                        </div>
                                    </li>

                                    <li class="movement-tree-node movement-tree-node--action is-current">
                                        <div class="movement-tree-row movement-tree-row--leaf">
                                            <span class="movement-tree-spacer" aria-hidden="true"></span>
                                            <span class="movement-tree-state" aria-hidden="true"></span>
                                            <span class="movement-tree-icon movement-tree-icon--action" aria-hidden="true"></span>
                                            <span class="movement-tree-text">
                                                <strong>Accepted for APP Consolidation</strong>
                                                <small>Oct 05, 2026 03:58 PM &middot; BACSEC-004 Demo User</small>
                                                <em>BAC Secretariat to BAC Secretariat</em>
                                                <em>Under APP Consolidation to Accepted</em>
                                                <em class="movement-tree-comment">Mock note: this is where users can see the active document location.</em>
                                            </span>
                                            <span class="movement-tree-badge">Current</span>
                                        </div>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    </ul>
                </details>
            </li>
        </ul>
    </section>
</div>
