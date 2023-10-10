'use client';
import { Card, Flex, Image, Text } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import Link from 'next/link';
import React from 'react';
import classes from './CourseItem.module.css';

type Props = {
  course: classroom_v1.Schema$Course;
};

export default function CourseItem({ course }: Props) {
  return (
    <Card
      shadow='sm'
      padding='lg'
      radius='sm'
      component={Link}
      className={classes.card}
      withBorder
      href={`/courses/${course.id}`}
    >
      <Card.Section>
        <Image
          src='https://images.unsplash.com/photo-1579227114347-15d08fc37cae?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=2550&q=80'
          h={160}
          alt='No way!'
        />
      </Card.Section>
      <Text fw={500} mt='lg' size='lg'>
        {course.name}
      </Text>
      <Flex mt='sm' justify='space-between'>
        <Text mt='xs' c='dimmed' size='sm'>
          {course.description}
        </Text>
        <Text mt='xs' c='dimmed' size='sm'>
          {course.section}
        </Text>
      </Flex>
    </Card>
  );
}
