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
    
    # Error field
    error = Column(String, nullable=True)
    
    # Relationship with tasks
    tasks = relationship("Task", back_populates="company", cascade="all, delete-orphan")
    
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
        
    def update_from_n8n(self, query_type: str, data: Dict[str, Any]) -> None:
        """Update company data from n8n workflow results"""
        print(f"\n=== Updating company data for {query_type} ===")
        data = data.get("output", data)
        
        if query_type == "profile":
            self.profile = data.get("profile", self.profile)
            print(f"Updated profile data: {list(self.profile.keys()) if isinstance(self.profile, dict) else 'Not a dict'}")
        elif query_type == "digital":
            self.digital = data.get("digital", self.digital)
            print(f"Updated digital data: {list(self.digital.keys()) if isinstance(self.digital, dict) else 'Not a dict'}")
        elif query_type == "timeline":
            self.timeline = data.get("timeline", data)
            print(f"Updated timeline data: {list(self.timeline.keys()) if isinstance(self.timeline, dict) else 'Not a dict'}")
        elif query_type == "products":
            self.products = data.get("products", self.products)
            print(f"Updated products data: {list(self.products.keys()) if isinstance(self.products, dict) else 'Not a dict'}")
        elif query_type == "jobs":
            self.jobs = data.get("jobs", self.jobs)
            print(f"Updated jobs data: {list(self.jobs.keys()) if isinstance(self.jobs, dict) else 'Not a dict'}")
        elif query_type == "csr":   
            self.csr = data.get("csr", self.csr)
            print(f"Updated csr data: {list(self.csr.keys()) if isinstance(self.csr, dict) else 'Not a dict'}")
        elif query_type == "press":
            self.press = data.get("press", self.press)
            print(f"Updated press data: {list(self.press.keys()) if isinstance(self.press, dict) else 'Not a dict'}")
        elif query_type == "team":
            self.team = data.get("team", self.team)
            print(f"Updated team data: {len(self.team) if isinstance(self.team, list) else 'Not a list'}")
        
        self.updated_at = datetime.utcnow()
        print("Update completed") 