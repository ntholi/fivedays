'use client';
import { Accordion, ActionIcon, Flex, Grid, Paper, Text } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import React from 'react';
import CourseWork from './CourseWork';
import Link from 'next/link';
import { IconExternalLink } from '@tabler/icons-react';

type Props = {
  course: classroom_v1.Schema$Course;
  courseWorkList?: classroom_v1.Schema$CourseWork[];
};

export default function Body({ course, courseWorkList }: Props) {
  return (
    <Grid mt='xl'>
      <Grid.Col span={{ base: 12, md: 4 }}>
        <Paper withBorder px='xl' py='sm'>
          <Flex justify='space-between'>
            <Text>{course.name}</Text>
            <ActionIcon
              variant='subtle'
              color='gray'
              component={Link}
              href={course.alternateLink || ''}
              target='_blank'
            >
              <IconExternalLink size={'1rem'} />
            </ActionIcon>
          </Flex>
        </Paper>
      </Grid.Col>
      <Grid.Col span={{ base: 12, md: 8 }}>
        <Accordion variant='separated' radius='xs'>
          {courseWorkList?.map((work) => (
            <CourseWork key={work.id} courseWork={work} />
          ))}
        </Accordion>
      </Grid.Col>
    </Grid>
  );
}
