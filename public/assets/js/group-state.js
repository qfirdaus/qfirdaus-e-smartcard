/**
 * State management untuk kumpulan-pengguna.php
 * Encapsulate semua state dalam class untuk better management
 */

class GroupStateManager {
  constructor() {
    // Menu access context
    this.menuContext = {
      groupID: null,
      dt: null,
      modules: []
    };
    
    // Group permissions context
    this.groupContext = {
      groupID: null,
      modulIDs: [],
      menuIDs: [],
      menusByModul: {},
      _modulesRaw: []
    };
    
    // Menu order dirty flag
    this.menuOrderDirty = false;
    
    // Last menu button (untuk context)
    this.lastMenuBtn = null;
  }
  
  // Menu context methods
  setMenuGroupID(groupID) {
    this.menuContext.groupID = groupID ? parseInt(groupID, 10) : null;
  }
  
  getMenuGroupID() {
    return this.menuContext.groupID;
  }
  
  setMenuDataTable(dt) {
    this.menuContext.dt = dt;
  }
  
  getMenuDataTable() {
    return this.menuContext.dt;
  }
  
  setMenuModules(modules) {
    this.menuContext.modules = Array.isArray(modules) ? modules : [];
  }
  
  // Group context methods
  setGroupID(groupID) {
    this.groupContext.groupID = groupID ? parseInt(groupID, 10) : null;
  }
  
  getGroupID() {
    return this.groupContext.groupID;
  }
  
  setModulIDs(modulIDs) {
    this.groupContext.modulIDs = Array.isArray(modulIDs) ? modulIDs : [];
  }
  
  getModulIDs() {
    return this.groupContext.modulIDs;
  }
  
  setMenuIDs(menuIDs) {
    this.groupContext.menuIDs = Array.isArray(menuIDs) ? menuIDs : [];
  }
  
  getMenuIDs() {
    return this.groupContext.menuIDs;
  }
  
  setMenusByModul(menusByModul) {
    this.groupContext.menusByModul = menusByModul || {};
  }
  
  getMenusByModul() {
    return this.groupContext.menusByModul;
  }
  
  setModulesRaw(modules) {
    this.groupContext._modulesRaw = Array.isArray(modules) ? modules : [];
  }
  
  getModulesRaw() {
    return this.groupContext._modulesRaw;
  }
  
  // Menu order methods
  setMenuOrderDirty(dirty) {
    this.menuOrderDirty = dirty;
  }
  
  isMenuOrderDirty() {
    return this.menuOrderDirty;
  }
  
  // Last menu button
  setLastMenuBtn(btn) {
    this.lastMenuBtn = btn;
  }
  
  getLastMenuBtn() {
    return this.lastMenuBtn;
  }
  
  // Clear all state
  clear() {
    this.menuContext = { groupID: null, dt: null, modules: [] };
    this.groupContext = { groupID: null, modulIDs: [], menuIDs: [], menusByModul: {}, _modulesRaw: [] };
    this.menuOrderDirty = false;
    this.lastMenuBtn = null;
  }
  
  // Clear menu context
  clearMenuContext() {
    this.menuContext = { groupID: null, dt: null, modules: [] };
  }
  
  // Clear group context
  clearGroupContext() {
    this.groupContext = { groupID: null, modulIDs: [], menuIDs: [], menusByModul: {}, _modulesRaw: [] };
  }
}

// Create global instance
window.GroupState = new GroupStateManager();









