package com.bens.core.common.service;

import com.bens.core.common.entity.BaseEntity;
import com.bens.core.common.repository.BaseRepository;
import java.util.List;
import java.util.UUID;
import org.springframework.http.HttpStatus;
import org.springframework.web.server.ResponseStatusException;

public abstract class BaseService<T extends BaseEntity, R extends BaseRepository<T>> {

    protected final R repository;

    protected BaseService(R repository) {
        this.repository = repository;
    }

    public List<T> findAll() {
        return repository.findAll();
    }

    public T findById(UUID id) {
        return repository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND));
    }

    public T save(T entity) {
        return repository.save(entity);
    }

    public void deleteById(UUID id) {
        if (!repository.existsById(id)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        }
        repository.deleteById(id);
    }
}
