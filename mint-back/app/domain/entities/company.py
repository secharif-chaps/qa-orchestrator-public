from datetime import datetime
from typing import Dict, List, Optional, Any
from sqlalchemy import Column, String, Integer, Boolean, DateTime, JSON, ForeignKey
from sqlalchemy.orm import relationship
from app.infrastructure.database.database import Base

class Company(Base):
    __tablename__ = "companies"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, index=True, nullable=False)
    website = Column(String, index=True, nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    
    # JSON fields for complex data structures
    profile = Column(JSON, default=dict)
    digital = Column(JSON, default=dict)
    timeline = Column(JSON, default=dict)
    products = Column(JSON, default=dict)
    jobs = Column(JSON, default=dict)
    csr = Column(JSON, default=dict)
    press = Column(JSON, default=dict)
    team = Column(JSON, default=list)
    
    # Tracking pending states
    pending_states = Column(JSON, default=dict)
    
    # Error field
    error = Column(String, nullable=True)
    
    def __init__(self, name: str, website: str):
        self.name = name
        self.website = website
        self.profile = {}
        self.digital = {}
        self.timeline = {}
        self.products = {}
        self.jobs = {}
        self.csr = {}
        self.press = {}
        self.team = []
        self.pending_states = {}
        
    def update_from_n8n(self, query_type: str, data: Dict[str, Any]) -> None:
        """Update company data from n8n workflow results"""
        if query_type == "profile":
            self.profile = data.get("profile", self.profile)
        elif query_type == "digital":
            self.digital = data.get("digital", self.digital)
        elif query_type == "timeline":
            self.timeline = data.get("timeline", self.timeline)
        elif query_type == "products":
            self.products = data.get("products", self.products)
        elif query_type == "jobs":
            self.jobs = data.get("jobs", self.jobs)
        elif query_type == "csr":   
            self.csr = data.get("csr", self.csr)
        elif query_type == "press":
            self.press = data.get("press", self.press)
        elif query_type == "team":
            self.team = data.get("team", self.team)
        
        self.updated_at = datetime.utcnow()
        
    def set_pending_state(self, query_type: str, is_pending: bool, error: Optional[str] = None) -> None:
        """Update pending state for a specific query type"""
        if not self.pending_states:
            self.pending_states = {}
            
        # Create a new pending states dict with all existing states
        new_pending_states = dict(self.pending_states)
        
        # Update or add the new pending state
        new_pending_states[query_type] = {
            "pending": is_pending,
            "error": error
        }
        
        # Update the pending states
        self.pending_states = new_pending_states
        self.updated_at = datetime.utcnow() 