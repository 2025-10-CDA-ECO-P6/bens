package com.bens.core.common.controller;

import com.bens.core.common.entity.BaseEntity;
import com.bens.core.common.service.BaseService;
import java.util.List;
import java.util.UUID;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;

public abstract class BaseController<T extends BaseEntity, S extends BaseService<T, ?>> {

    protected final S service;

    protected BaseController(S service) {
        this.service = service;
    }

    @GetMapping
    public List<T> findAll() {
        return service.findAll();
    }

    @GetMapping("/{id}")
    public T findById(@PathVariable UUID id) {
        return service.findById(id);
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public T create(@RequestBody T entity) {
        return service.save(entity);
    }

    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void delete(@PathVariable UUID id) {
        service.deleteById(id);
    }
}
